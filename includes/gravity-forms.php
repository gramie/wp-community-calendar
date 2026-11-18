<?php
/**
 * District and society choices, read from Gravity Forms entries.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns [ entry_id => title ] for 'district' or 'society', using the form and
 * field chosen under Settings > Community Calendar.
 */
function cc_gf_choices( $type ) {
	static $cache = array();
	if ( isset( $cache[ $type ] ) ) {
		return $cache[ $type ];
	}
	$out = array();
	if ( class_exists( 'GFAPI' ) ) {
		$form_id = absint( get_option( "cc_{$type}_form" ) );
		$field   = trim( (string) get_option( "cc_{$type}_field", '1' ) );
		if ( $form_id && '' !== $field ) {
			$entries = GFAPI::get_entries(
				$form_id,
				array( 'status' => 'active' ),
				null,
				array( 'offset' => 0, 'page_size' => 1000 )
			);
			if ( ! is_wp_error( $entries ) ) {
				foreach ( $entries as $entry ) {
					$title = trim( (string) rgar( $entry, $field ) );
					if ( '' !== $title ) {
						$out[ (int) $entry['id'] ] = $title;
					}
				}
				asort( $out, SORT_NATURAL | SORT_FLAG_CASE );
			}
		}
	}
	return $cache[ $type ] = $out;
}

/** Current title for an event's district/society (live from Gravity Forms, falling back to the saved title). */
function cc_gf_label( $type, $post_id ) {
	$id      = (int) get_post_meta( $post_id, "_cc_{$type}_id", true );
	$choices = cc_gf_choices( $type );
	if ( $id && isset( $choices[ $id ] ) ) {
		return $choices[ $id ];
	}
	return (string) get_post_meta( $post_id, "_cc_{$type}_title", true );
}
