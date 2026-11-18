<?php
/**
 * Admin event fields: meta box, saving and validation.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'add_meta_boxes_cc_event', function () {
	add_meta_box( 'cc_details', __( 'Event details', 'community-calendar' ), function ( $post ) {
		echo cc_get_meta_box_html( $post->ID, false ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the template.
	}, 'cc_event', 'normal', 'high' );
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( $screen && 'cc_event' === $screen->post_type ) {
		cc_enqueue_event_form_assets();
	}
} );

/**
 * The event fields as HTML. Used by the admin meta box and the front-end form.
 * While $GLOBALS['cc_prefill'] is set (after a failed front-end submit), cc_meta() returns those values.
 */
function cc_get_meta_box_html( $post_id, $front = false ) {
	$post_id  = (int) $post_id;
	$weekdays = isset( $GLOBALS['cc_prefill']['weekdays'] )
		? (array) $GLOBALS['cc_prefill']['weekdays']
		: (array) get_post_meta( $post_id, '_cc_weekdays', true );

	return cc_get_template( 'meta-box-fields', array(
		'post_id'   => $post_id,
		'front'     => (bool) $front,
		'freq'      => cc_meta( $post_id, 'freq', '' ),
		'weekdays'  => array_map( 'intval', $weekdays ),
		'nth'       => (int) cc_meta( $post_id, 'nth', 1 ),
		'nth_day'   => (int) cc_meta( $post_id, 'nth_day', 1 ),
		'districts' => cc_gf_choices( 'district' ),
		'societies' => cc_gf_choices( 'society' ),
		'sel_d'     => (int) cc_meta( $post_id, 'district_id', 0 ),
		'sel_s'     => (int) cc_meta( $post_id, 'society_id', 0 ),
		'types'     => cc_event_type_choices(),
		'sel_types' => cc_event_type_ids( $post_id ),
		'v'         => array(
			'start'    => cc_to_input( cc_meta( $post_id, 'start' ) ),
			'end'      => cc_to_input( cc_meta( $post_id, 'end' ) ),
			'interval' => cc_meta( $post_id, 'interval', 1 ),
			'until'    => cc_meta( $post_id, 'until' ),
			'location' => cc_meta( $post_id, 'location' ),
			'cost'     => cc_meta( $post_id, 'cost' ),
			'website'  => cc_meta( $post_id, 'website' ),
			'email'    => cc_meta( $post_id, 'email' ),
			'phone'    => cc_meta( $post_id, 'phone' ),
		),
	) );
}

add_action( 'save_post_cc_event', 'cc_save_event', 10, 2 );

function cc_save_event( $post_id, $post ) {
	if ( ! isset( $_POST['cc_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cc_nonce'] ) ), 'cc_save_event' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$in = isset( $_POST['cc'] ) && is_array( $_POST['cc'] ) ? wp_unslash( $_POST['cc'] ) : array();
	$errors = cc_save_event_meta( $post_id, $in );
	if ( $errors ) {
		set_transient( 'cc_admin_errors_' . get_current_user_id(), $errors, 120 );
	}
}

/** Validates and stores the event fields (shared by the admin meta box and the front-end form). */
function cc_save_event_meta( $post_id, array $in ) {
	$g  = function ( $k ) use ( $in ) {
		return isset( $in[ $k ] ) && is_scalar( $in[ $k ] ) ? (string) $in[ $k ] : '';
	};

	// Dates and recurrence. If they are invalid the stored values are left untouched and the errors are returned.
	$errors = cc_validate_event_input( $in );
	if ( ! $errors ) {
		$start    = DateTimeImmutable::createFromMutable( cc_parse_local( $g( 'start' ) ) );
		$end_in   = cc_parse_local( $g( 'end' ) );
		$end      = $end_in ? DateTimeImmutable::createFromMutable( $end_in ) : $start->modify( '+1 hour' );
		$duration = $end->getTimestamp() - $start->getTimestamp();

		$freq = $g( 'freq' );
		if ( ! in_array( $freq, array( 'daily', 'weekly', 'monthly', 'monthly_nth', 'yearly' ), true ) ) {
			$freq = '';
		}
		$interval = max( 1, min( 99, (int) $g( 'interval' ) ) );
		$weekdays = array();
		if ( isset( $in['weekdays'] ) && is_array( $in['weekdays'] ) ) {
			foreach ( $in['weekdays'] as $d ) {
				$d = (int) $d;
				if ( $d >= 1 && $d <= 7 ) {
					$weekdays[] = $d;
				}
			}
			$weekdays = array_values( array_unique( $weekdays ) );
			sort( $weekdays );
		}
		$nth     = (int) $g( 'nth' );
		$nth     = in_array( $nth, array( 1, 2, 3, 4, -1 ), true ) ? $nth : 1;
		$nth_day = (int) $g( 'nth_day' );
		$nth_day = ( $nth_day >= 1 && $nth_day <= 7 ) ? $nth_day : (int) $start->format( 'N' );

		$until = '';
		if ( $freq ) {
			// The start moves to the first date matching the rule, so the calendar and iCal feed agree.
			$start    = cc_align_start( $start, $freq, $interval, $weekdays, $nth, $nth_day );
			$end      = $start->setTimestamp( $start->getTimestamp() + $duration );
			$until_dt = cc_effective_until( $start, $g( 'until' ) ); // Never more than one year after the start.
			$until    = $until_dt->format( 'Y-m-d' );
			$last     = $until_dt->setTimestamp( $until_dt->getTimestamp() + $duration )->format( 'Y-m-d H:i:s' );
		} else {
			$last = $end->format( 'Y-m-d H:i:s' );
		}

		update_post_meta( $post_id, '_cc_start', $start->format( 'Y-m-d H:i:s' ) );
		update_post_meta( $post_id, '_cc_end', $end->format( 'Y-m-d H:i:s' ) );
		update_post_meta( $post_id, '_cc_freq', $freq );
		update_post_meta( $post_id, '_cc_interval', $interval );
		update_post_meta( $post_id, '_cc_until', $until );
		update_post_meta( $post_id, '_cc_weekdays', $weekdays );
		update_post_meta( $post_id, '_cc_nth', $nth );
		update_post_meta( $post_id, '_cc_nth_day', $nth_day );
		update_post_meta( $post_id, '_cc_last', $last ); // Indexed "last possible moment" for efficient queries.
	}

	// Simple text fields.
	$location = sanitize_text_field( $g( 'location' ) );
	update_post_meta( $post_id, '_cc_location', $location );
	cc_update_geocode( $post_id, $location );
	update_post_meta( $post_id, '_cc_cost', sanitize_text_field( $g( 'cost' ) ) );
	update_post_meta( $post_id, '_cc_website', esc_url_raw( $g( 'website' ) ) );
	update_post_meta( $post_id, '_cc_email', sanitize_email( $g( 'email' ) ) );
	update_post_meta( $post_id, '_cc_phone', preg_replace( '/[^0-9+\-\s().xX]/', '', $g( 'phone' ) ) );

	// District & society (Gravity Forms entry IDs, plus a title snapshot as fallback).
	foreach ( array( 'district', 'society' ) as $type ) {
		$eid     = absint( $g( $type ) );
		$choices = cc_gf_choices( $type );
		if ( $eid && isset( $choices[ $eid ] ) ) {
			update_post_meta( $post_id, "_cc_{$type}_id", $eid );
			update_post_meta( $post_id, "_cc_{$type}_title", $choices[ $eid ] );
		} elseif ( ! $eid ) {
			delete_post_meta( $post_id, "_cc_{$type}_id" );
			delete_post_meta( $post_id, "_cc_{$type}_title" );
		}
		// If the entry ID isn't in the current list (e.g. Gravity Forms inactive), keep the stored value.
	}

	// Event types: any number of the types the administrator has defined.
	$choices  = cc_event_type_choices();
	$type_ids = array();
	if ( isset( $in['types'] ) && is_array( $in['types'] ) ) {
		foreach ( $in['types'] as $tid ) {
			$tid = is_scalar( $tid ) ? absint( $tid ) : 0;
			if ( isset( $choices[ $tid ] ) ) {
				$type_ids[] = $tid;
			}
		}
	}
	wp_set_object_terms( $post_id, array_values( array_unique( $type_ids ) ), 'cc_event_type' );
	return $errors;
}

/** Returns a list of error messages for the date fields of a submitted event (empty when valid). */
function cc_validate_event_input( array $in ) {
	$errors = array();
	$val    = function ( $k ) use ( $in ) {
		return isset( $in[ $k ] ) && is_scalar( $in[ $k ] ) ? trim( (string) $in[ $k ] ) : '';
	};
	$start = cc_parse_local( $val( 'start' ) );
	if ( ! $start ) {
		$errors[] = __( 'Please enter a valid start date and time.', 'community-calendar' );
		return $errors;
	}
	if ( '' !== $val( 'end' ) ) {
		$end = cc_parse_local( $val( 'end' ) );
		if ( ! $end ) {
			$errors[] = __( 'Please enter a valid end date and time.', 'community-calendar' );
		} elseif ( $end < $start ) {
			$errors[] = __( 'The event cannot end before it starts.', 'community-calendar' );
		}
	}
	$until = $val( 'until' );
	if ( $val( 'freq' ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $until ) && $until < $start->format( 'Y-m-d' ) ) {
		$errors[] = __( 'The repeat-until date cannot be before the start date.', 'community-calendar' );
	}
	return $errors;
}

// Show validation problems from an admin save on the next screen.
add_action( 'admin_notices', function () {
	$key    = 'cc_admin_errors_' . get_current_user_id();
	$errors = get_transient( $key );
	if ( $errors ) {
		delete_transient( $key );
		echo cc_get_template( 'error-notice', array( 'errors' => (array) $errors ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the template.
	}
} );
