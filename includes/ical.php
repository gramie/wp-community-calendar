<?php
/**
 * iCalendar feed (output is plain text, so there is no template).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * iCalendar feed
 *
 *   /events.ics                      all events
 *   /events.ics?cc_district=ID       filtered by district entry ID
 *   /events.ics?cc_society=ID        filtered by society entry ID
 *   /events.ics?cc_type=TERM_ID      filtered by event type
 *   /events.ics?cc_event=POST_ID     a single event
 *
 * Without pretty permalinks use /?cc_ical=1 instead.
 * Recurring events are exported as one VEVENT with an RRULE.
 * ---------------------------------------------------------------------- */

add_action( 'init', function () {
	add_rewrite_rule( '^events\.ics$', 'index.php?cc_ical=1', 'top' );
	if ( get_option( 'cc_rewrite_version' ) !== '2' ) {
		flush_rewrite_rules( false );
		update_option( 'cc_rewrite_version', '2' );
	}
}, 20 );

add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'cc_ical';
	return $vars;
} );

// 1. Short-circuit core's 404 handling for feed requests.
add_filter( 'pre_handle_404', function ( $preempt, $query ) {
    if ( '' !== (string) get_query_var( 'cc_ical' ) ) {
        return true; // Non-false short-circuits handle_404().
    }
    return $preempt;
}, 10, 2 );

function cc_ical_url( $args = array() ) {
	$args = array_filter( $args );
	if ( get_option( 'permalink_structure' ) ) {
		return add_query_arg( $args, home_url( '/events.ics' ) );
	}
	return add_query_arg( array_merge( array( 'cc_ical' => 1 ), $args ), home_url( '/' ) );
}

function cc_ical_escape( $text ) {
	return str_replace( array( '\\', ';', ',', "\r\n", "\n", "\r" ), array( '\\\\', '\;', '\,', '\n', '\n', '\n' ), (string) $text );
}

/** Folds a content line to the 75-octet limit without splitting multibyte characters. */
function cc_ical_fold( $line ) {
	$out = '';
	while ( strlen( $line ) > 70 ) {
		$cut = 70;
		while ( $cut > 0 && ( ord( $line[ $cut ] ) & 0xC0 ) === 0x80 ) {
			$cut--;
		}
		$out  .= substr( $line, 0, $cut ) . "\r\n ";
		$line  = substr( $line, $cut );
	}
	return $out . $line;
}

/** Builds a VTIMEZONE block for a named timezone from its transitions. */
function cc_ical_vtimezone( DateTimeZone $tz ) {
	$fmt_off = function ( $s ) {
		$sign = $s < 0 ? '-' : '+';
		$s    = abs( $s );
		return sprintf( '%s%02d%02d', $sign, intdiv( $s, 3600 ), intdiv( $s % 3600, 60 ) );
	};
	$tr = $tz->getTransitions( gmmktime( 0, 0, 0, 1, 1, 2000 ), gmmktime( 0, 0, 0, 1, 1, (int) gmdate( 'Y' ) + 10 ) );
	if ( ! $tr ) {
		$tr = array( array( 'ts' => 0, 'offset' => 0, 'isdst' => false, 'abbr' => 'UTC' ) );
	}
	$lines = array( 'BEGIN:VTIMEZONE', 'TZID:' . $tz->getName() );

	// State in effect before the first listed transition.
	$first = $tr[0];
	$lines[] = $first['isdst'] ? 'BEGIN:DAYLIGHT' : 'BEGIN:STANDARD';
	$lines[] = 'DTSTART:19700101T000000';
	$lines[] = 'TZOFFSETFROM:' . $fmt_off( $first['offset'] );
	$lines[] = 'TZOFFSETTO:' . $fmt_off( $first['offset'] );
	$lines[] = 'TZNAME:' . $first['abbr'];
	$lines[] = $first['isdst'] ? 'END:DAYLIGHT' : 'END:STANDARD';

	$prev = $first['offset'];
	foreach ( array_slice( $tr, 1 ) as $t ) {
		$type    = $t['isdst'] ? 'DAYLIGHT' : 'STANDARD';
		$lines[] = 'BEGIN:' . $type;
		$lines[] = 'DTSTART:' . gmdate( 'Ymd\THis', $t['ts'] + $prev );
		$lines[] = 'TZOFFSETFROM:' . $fmt_off( $prev );
		$lines[] = 'TZOFFSETTO:' . $fmt_off( $t['offset'] );
		$lines[] = 'TZNAME:' . $t['abbr'];
		$lines[] = 'END:' . $type;
		$prev    = $t['offset'];
	}
	$lines[] = 'END:VTIMEZONE';
	return $lines;
}

function cc_ical_event_lines( WP_Post $post, DateTimeZone $tz, $use_utc ) {
	$id    = $post->ID;
	$start = cc_meta( $id, 'start' );
	$end   = cc_meta( $id, 'end' );
	if ( ! $start ) {
		return array();
	}
	$dt = function ( $mysql, $prop ) use ( $tz, $use_utc ) {
		if ( $use_utc ) {
			$d = ( new DateTimeImmutable( $mysql, $tz ) )->setTimezone( new DateTimeZone( 'UTC' ) );
			return $prop . ':' . $d->format( 'Ymd\THis\Z' );
		}
		return $prop . ';TZID=' . $tz->getName() . ':' . str_replace( array( '-', ' ', ':' ), array( '', 'T', '' ), $mysql );
	};

	$host  = wp_parse_url( home_url(), PHP_URL_HOST );
	if (!$end) {
	    $endDT = new DateTimeImmutable($end);
		$newEndDT = $endDT->add(new DateInterval('PT1H'));
		$end = $newEndDT->format('Y-m-d H:i:s');
	}
	$lines = array(
		'BEGIN:VEVENT',
		'UID:cc-' . $id . '@' . $host,
		'DTSTAMP:' . gmdate( 'Ymd\THis\Z' ),
		'LAST-MODIFIED:' . gmdate( 'Ymd\THis\Z', strtotime( $post->post_modified_gmt . ' UTC' ) ),
		$dt( $start, 'DTSTART' ),
		$dt( $end ? $end : $start, 'DTEND' ),
		'SUMMARY:' . cc_ical_escape( get_the_title( $post ) ),
		'URL:' . esc_url(get_permalink( $post )),
	);

	// Recurrence.
	$freq = cc_meta( $id, 'freq' );
	if ( $freq ) {
		$interval = max( 1, (int) cc_meta( $id, 'interval', 1 ) );
		$rule     = array( 'FREQ=' . ( 'monthly_nth' === $freq ? 'MONTHLY' : strtoupper( $freq ) ), 'INTERVAL=' . $interval );
		$start_dt = new DateTimeImmutable( $start, $tz );
		if ( 'weekly' === $freq ) {
			$map = array( 1 => 'MO', 2 => 'TU', 3 => 'WE', 4 => 'TH', 5 => 'FR', 6 => 'SA', 7 => 'SU' );
			$wd  = array_filter( array_map( 'intval', (array) get_post_meta( $id, '_cc_weekdays', true ) ) );
			if ( ! $wd ) {
				$wd = array( (int) $start_dt->format( 'N' ) );
			}
			$rule[] = 'BYDAY=' . implode( ',', array_map( function ( $d ) use ( $map ) {
				return $map[ $d ];
			}, $wd ) );
		} elseif ( 'monthly' === $freq ) {
			$rule[] = 'BYMONTHDAY=' . (int) $start_dt->format( 'j' );
		} elseif ( 'monthly_nth' === $freq ) {
			$map     = array( 1 => 'MO', 2 => 'TU', 3 => 'WE', 4 => 'TH', 5 => 'FR', 6 => 'SA', 7 => 'SU' );
			$nth     = (int) cc_meta( $id, 'nth', 1 );
			$nth_day = (int) cc_meta( $id, 'nth_day', (int) $start_dt->format( 'N' ) );
			$rule[]  = 'BYDAY=' . ( -1 === $nth ? '-1' : max( 1, $nth ) ) . $map[ max( 1, min( 7, $nth_day ) ) ];
		}
		// Always end the series: at most one year after the start.
		$u      = cc_effective_until( $start_dt, cc_meta( $id, 'until' ) )->setTimezone( new DateTimeZone( 'UTC' ) );
		$rule[] = 'UNTIL=' . $u->format( 'Ymd\THis\Z' );
		$lines[] = 'RRULE:' . implode( ';', $rule );
	}

	// Description.
	$desc  = trim( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ) );
	$extra = array();
	if ( $v = cc_meta( $id, 'cost' ) ) {
		$extra[] = __( 'Cost', 'community-calendar' ) . ': ' . $v;
	}
	if ( $v = cc_gf_label( 'district', $id ) ) {
		$extra[] = __( 'District', 'community-calendar' ) . ': ' . $v;
	}
	if ( $v = cc_gf_label( 'society', $id ) ) {
		$extra[] = __( 'Society', 'community-calendar' ) . ': ' . $v;
	}
	if ( $v = cc_meta( $id, 'website' ) ) {
		$extra[] = __( 'Website', 'community-calendar' ) . ': ' . $v;
	}
	if ( $v = cc_meta( $id, 'phone' ) ) {
		$extra[] = __( 'Telephone', 'community-calendar' ) . ': ' . $v;
	}
	if ( $extra ) {
		$desc = trim( $desc . "\n\n" . implode( "\n", $extra ) );
	}
	if ( '' !== $desc ) {
		$lines[] = 'DESCRIPTION:' . cc_ical_escape( $desc );
	}
	if ( $v = cc_meta( $id, 'location' ) ) {
		$lines[] = 'LOCATION:' . cc_ical_escape( $v );
	}
	if ( ( $v = cc_meta( $id, 'email' ) ) && is_email( $v ) ) {
		$lines[] = 'ORGANIZER:mailto:' . $v;
	}
	$cats = array_filter( array_merge( cc_event_type_labels( $id ), array( cc_gf_label( 'district', $id ), cc_gf_label( 'society', $id ) ) ) );
	if ( $cats ) {
		$lines[] = 'CATEGORIES:' . implode( ',', array_map( 'cc_ical_escape', $cats ) );
	}
	$lines[] = 'END:VEVENT';
	return $lines;
}

add_action( 'template_redirect', 'cc_serve_ical', 1 );

function cc_serve_ical() {
	if ( ! get_query_var( 'cc_ical' ) ) {
		return;
	}
	// phpcs:disable WordPress.Security.NonceVerification -- public, read-only feed.
	$district = isset( $_GET['cc_district'] ) ? absint( $_GET['cc_district'] ) : 0;
	$society  = isset( $_GET['cc_society'] ) ? absint( $_GET['cc_society'] ) : 0;
	$type     = isset( $_GET['cc_type'] ) ? absint( $_GET['cc_type'] ) : 0;
	$single   = isset( $_GET['cc_event'] ) ? absint( $_GET['cc_event'] ) : 0;
	$search   = isset( $_GET['cc_s'] ) && is_string( $_GET['cc_s'] ) ? sanitize_text_field( wp_unslash( $_GET['cc_s'] ) ) : '';
	// phpcs:enable

	$tz   = wp_timezone();
	$args = array(
		'post_type'      => 'cc_event',
		'post_status'    => 'publish',
		'posts_per_page' => 1000,
		'no_found_rows'  => true,
		'suppress_filters' => false, // Needed for the title search.
		'cc_title_search'  => $search,
		'meta_query'     => array(
			'relation' => 'AND',
			array( 'key' => '_cc_start', 'compare' => 'EXISTS' ),
			array(
				'key'     => '_cc_last',
				'value'   => ( new DateTimeImmutable( '-6 months', $tz ) )->format( 'Y-m-d H:i:s' ),
				'compare' => '>=',
				'type'    => 'DATETIME',
			),
		),
	);
	if ( $single ) {
		$args['p'] = $single;
		unset( $args['meta_query'][1] ); // Single events are always exportable.
		unset( $args['meta_query'][2] );
	}
	if ( $district ) {
		$args['meta_query'][] = array( 'key' => '_cc_district_id', 'value' => $district );
	}
	if ( $society ) {
		$args['meta_query'][] = array( 'key' => '_cc_society_id', 'value' => $society );
	}
	if ( $type ) {
		$args['tax_query'] = array(
			array( 'taxonomy' => 'cc_event_type', 'field' => 'term_id', 'terms' => $type ),
		);
	}
	$posts = get_posts( $args );
	//print_r($posts);

	$name    = get_bloginfo( 'name' ) . ' – ' . __( 'Events', 'community-calendar' );
	$use_utc = ( 'UTC' === $tz->getName() || preg_match( '/^[+-]/', $tz->getName() ) );
	$lines   = array(
		'BEGIN:VCALENDAR',
		'VERSION:2.0',
		'PRODID:-//Community Calendar//WordPress//EN',
		'CALSCALE:GREGORIAN',
		'METHOD:PUBLISH',
		'X-WR-CALNAME:' . cc_ical_escape( $name ),
		'REFRESH-INTERVAL;VALUE=DURATION:PT1H',
		'X-PUBLISHED-TTL:PT1H',
	);
	if ( ! $use_utc ) {
		$lines[] = 'X-WR-TIMEZONE:' . $tz->getName();
		$lines   = array_merge( $lines, cc_ical_vtimezone( $tz ) );
	}
	foreach ( $posts as $post ) {
		$lines = array_merge( $lines, cc_ical_event_lines( $post, $tz, $use_utc ) );
	}
	$lines[] = 'END:VCALENDAR';

	$body = implode( "\r\n", array_map( 'cc_ical_fold', $lines ) ) . "\r\n";

	status_header( 200 );
	nocache_headers();
	header( 'Content-Type: text/calendar; charset=utf-8' );
	header( 'Content-Disposition: inline; filename="events.ics"' );
	header( 'Cache-Control: public, max-age=900' );
	echo $body; // phpcs:ignore WordPress.Security.EscapeOutput -- iCalendar text, escaped per RFC 5545.
	echo " ";
	exit;
}
