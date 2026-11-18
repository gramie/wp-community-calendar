<?php
/**
 * Event post type and admin list columns.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'cc_register_post_type' );

function cc_register_post_type() {
	register_post_type( 'cc_event', array(
		'labels'          => array(
			'name'               => __( 'Events', 'community-calendar' ),
			'singular_name'      => __( 'Event', 'community-calendar' ),
			'add_new_item'       => __( 'Add New Event', 'community-calendar' ),
			'edit_item'          => __( 'Edit Event', 'community-calendar' ),
			'new_item'           => __( 'New Event', 'community-calendar' ),
			'view_item'          => __( 'View Event', 'community-calendar' ),
			'search_items'       => __( 'Search Events', 'community-calendar' ),
			'not_found'          => __( 'No events found', 'community-calendar' ),
			'menu_name'          => __( 'Calendar', 'community-calendar' ),
			'featured_image'     => __( 'Event image', 'community-calendar' ),
			'set_featured_image' => __( 'Upload / select event image', 'community-calendar' ),
			'remove_featured_image' => __( 'Remove event image', 'community-calendar' ),
			'use_featured_image' => __( 'Use as event image', 'community-calendar' ),
		),
		'public'          => true,
		'has_archive'     => true,
		'rewrite'         => array( 'slug' => 'events' ),
		'menu_icon'       => 'dashicons-calendar-alt',
		'supports'        => array( 'title', 'editor', 'thumbnail', 'author' ),
		'capability_type' => array( 'cc_event', 'cc_events' ),
		'map_meta_cap'    => true,
		'show_in_rest'    => true,
	) );
}

// Directors only see and pick their own uploads in the media modal.
add_filter( 'ajax_query_attachments_args', function ( $args ) {
	if ( ! current_user_can( 'edit_others_cc_events' ) && ! current_user_can( 'edit_others_posts' ) ) {
		$args['author'] = get_current_user_id();
	}
	return $args;
} );

add_filter( 'manage_cc_event_posts_columns', function ( $cols ) {
	$new = array();
	foreach ( $cols as $k => $v ) {
		$new[ $k ] = $v;
		if ( 'title' === $k ) {
			$new['cc_when']   = __( 'Starts', 'community-calendar' );
			$new['cc_repeat'] = __( 'Repeats', 'community-calendar' );
			$new['cc_where']  = __( 'District / Society', 'community-calendar' );
		}
	}
	return $new;
} );

add_action( 'manage_cc_event_posts_custom_column', function ( $col, $post_id ) {
	if ( 'cc_when' === $col ) {
		$s = cc_meta( $post_id, 'start' );
		echo $s ? esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), ( new DateTimeImmutable( $s, wp_timezone() ) )->getTimestamp() ) ) : '—';
	} elseif ( 'cc_repeat' === $col ) {
		$f = cc_meta( $post_id, 'freq' );
		echo $f ? esc_html( cc_freq_label( $f ) ) : '—';
	} elseif ( 'cc_where' === $col ) {
		$parts = array_filter( array( cc_gf_label( 'district', $post_id ), cc_gf_label( 'society', $post_id ) ) );
		echo $parts ? esc_html( implode( ' / ', $parts ) ) : '—';
	}
}, 10, 2 );

/* -------------------------------------------------------------------------
 * Event types (taxonomy). Administrators manage the list under
 * Calendar > Event Types; directors pick any number of them when they create an event.
 * ---------------------------------------------------------------------- */

add_action( 'init', 'cc_register_taxonomy' );

function cc_register_taxonomy() {
	register_taxonomy( 'cc_event_type', 'cc_event', array(
		'labels'             => array(
			'name'          => __( 'Event Types', 'community-calendar' ),
			'singular_name' => __( 'Event Type', 'community-calendar' ),
			'menu_name'     => __( 'Event Types', 'community-calendar' ),
			'all_items'     => __( 'All Event Types', 'community-calendar' ),
			'add_new_item'  => __( 'Add New Event Type', 'community-calendar' ),
			'edit_item'     => __( 'Edit Event Type', 'community-calendar' ),
			'search_items'  => __( 'Search Event Types', 'community-calendar' ),
		),
		'public'             => false,
		'show_ui'            => true,
		'show_admin_column'  => true,
		'show_in_quick_edit' => false,
		'show_in_rest'       => false,
		'hierarchical'       => false,
		'rewrite'            => false,
		'query_var'          => false,
		'meta_box_cb'        => false, // Picked with the event's own fields instead.
		'capabilities'       => array( 'assign_terms' => 'edit_cc_events' ),
	) );
}

/** [ term ID => name ] of every event type, sorted by name. */
function cc_event_type_choices() {
	static $cache = null;
	if ( null === $cache ) {
		$cache = array();
		$terms = get_terms( array( 'taxonomy' => 'cc_event_type', 'hide_empty' => false ) );
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$cache[ (int) $term->term_id ] = $term->name;
			}
			asort( $cache, SORT_NATURAL | SORT_FLAG_CASE );
		}
	}
	return $cache;
}

/** IDs of the event's types. Honours $GLOBALS['cc_prefill'] after a failed front-end submit. */
function cc_event_type_ids( $post_id ) {
	if ( isset( $GLOBALS['cc_prefill']['type_ids'] ) ) {
		return array_map( 'intval', (array) $GLOBALS['cc_prefill']['type_ids'] );
	}
	$ids = wp_get_object_terms( (int) $post_id, 'cc_event_type', array( 'fields' => 'ids' ) );
	return is_wp_error( $ids ) ? array() : array_map( 'intval', $ids );
}

/** Names of the event's types, sorted alphabetically. */
function cc_event_type_labels( $post_id ) {
	$names = wp_get_object_terms( (int) $post_id, 'cc_event_type', array( 'fields' => 'names' ) );
	if ( is_wp_error( $names ) ) {
		return array();
	}
	natcasesort( $names );
	return array_values( $names );
}

/** Resolves a shortcode value (term ID or slug) to a term ID; 0 if it isn't an event type. */
function cc_resolve_event_type( $value ) {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return 0;
	}
	if ( ctype_digit( $value ) ) {
		return isset( cc_event_type_choices()[ (int) $value ] ) ? (int) $value : 0;
	}
	$term = get_term_by( 'slug', sanitize_title( $value ), 'cc_event_type' );
	return $term ? (int) $term->term_id : 0;
}
