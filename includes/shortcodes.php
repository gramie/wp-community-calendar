<?php
/**
 * Public calendar: [community_calendar] (list and month views, filters) and the single event page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode( 'community_calendar', 'cc_shortcode' );

/** Reads and validates the visitor's filters from the query string (shortcode attributes pin district/society). */
function cc_filter_state( $atts ) {
	// phpcs:disable WordPress.Security.NonceVerification -- public, read-only filters.
	$get  = function ( $k ) {
		return isset( $_GET[ $k ] ) && is_string( $_GET[ $k ] ) ? sanitize_text_field( wp_unslash( $_GET[ $k ] ) ) : '';
	};
	// phpcs:enable
	$date = function ( $v ) {
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ) {
			$d = date_create_immutable( $v, wp_timezone() );
			if ( $d && $d->format( 'Y-m-d' ) === $v ) {
				return $v;
			}
		}
		return '';
	};
	$f = array(
		'district' => $atts['district'] ? absint( $atts['district'] ) : absint( $get( 'cc_district' ) ),
		'society'  => $atts['society'] ? absint( $atts['society'] ) : absint( $get( 'cc_society' ) ),
		'search'   => mb_substr( $get( 'cc_s' ), 0, 100 ),
		'from'     => $date( $get( 'cc_from' ) ),
		'to'       => $date( $get( 'cc_to' ) ),
		'type'     => $atts['type'] ? cc_resolve_event_type( $atts['type'] ) : cc_resolve_event_type( $get( 'cc_type' ) ),
	);
	if ( $f['from'] && $f['to'] && $f['from'] > $f['to'] ) {
		list( $f['from'], $f['to'] ) = array( $f['to'], $f['from'] );
	}
	return $f;
}

function cc_shortcode( $atts ) {
	$atts = shortcode_atts( array(
		'view'     => 'list',   // Default view: list or month.
		'switcher' => 'yes',    // Let visitors switch between the list and calendar views.
		'days'     => 90,
		'filters'  => 'yes',
		'ical'     => 'yes',
		'district' => '',
		'society'  => '',
		'type'     => '',       // Event type slug or ID.
	), $atts, 'community_calendar' );
	cc_enqueue_styles();

	$f    = cc_filter_state( $atts );
	$view = 'month' === $atts['view'] ? 'month' : 'list';
	if ( 'no' !== $atts['switcher'] ) {
		$requested = isset( $_GET['cc_view'] ) && is_string( $_GET['cc_view'] ) ? sanitize_key( wp_unslash( $_GET['cc_view'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( in_array( $requested, array( 'list', 'month' ), true ) ) {
			$view = $requested;
		}
	}

	$switcher = '';
	if ( 'no' !== $atts['switcher'] ) {
		$switcher = cc_get_template( 'view-switcher', array(
			'views' => array(
				array(
					'label'   => __( 'List', 'community-calendar' ),
					'url'     => add_query_arg( 'cc_view', 'list' ),
					'current' => 'list' === $view,
				),
				array(
					'label'   => __( 'Calendar', 'community-calendar' ),
					'url'     => add_query_arg( 'cc_view', 'month' ),
					'current' => 'month' === $view,
				),
			),
		) );
	}

	$filters = 'no' !== $atts['filters']
		? cc_render_filters( $f, ! $atts['district'], ! $atts['society'], ! $atts['type'], $view )
		: '';

	$subscribe = '';
	if ( 'no' !== $atts['ical'] ) {
		$ical_url  = cc_ical_url( array(
			'cc_district' => $f['district'],
			'cc_society'  => $f['society'],
			'cc_type'     => $f['type'],
			'cc_s'        => $f['search'],
		) );
		$subscribe = cc_get_template( 'subscribe-links', array(
			'ical_url'   => $ical_url,
			'webcal_url' => preg_replace( '#^https?://#', 'webcal://', $ical_url ),
		) );
	}

	$body = ( 'month' === $view )
		? cc_render_month( $f )
		: cc_render_list( $f, max( 1, (int) $atts['days'] ) );

	return cc_get_template( 'calendar', compact( 'switcher', 'filters', 'subscribe', 'body' ) );
}

/** Filter bar: title search, date range, event type, district and society. */
function cc_render_filters( array $f, $show_d, $show_s, $show_t, $view ) {
	$qid    = get_queried_object_id();
	$page   = $qid ? get_permalink( $qid ) : '';
	$hidden = array( 'cc_view' => $view ); // Keep the chosen view when the form is submitted.
	if ( $qid && ! get_option( 'permalink_structure' ) ) {
		// Plain permalinks identify the page via the query string, which a GET form would otherwise drop.
		$hidden[ 'page' === get_post_type( $qid ) ? 'page_id' : 'p' ] = (int) $qid;
	}

	$defs    = array(
		'type'     => array( $show_t, $f['type'], __( 'All event types', 'community-calendar' ), cc_event_type_choices() ),
		'district' => array( $show_d, $f['district'], __( 'All districts', 'community-calendar' ), $show_d ? cc_gf_choices( 'district' ) : array() ),
		'society'  => array( $show_s, $f['society'], __( 'All societies', 'community-calendar' ), $show_s ? cc_gf_choices( 'society' ) : array() ),
	);
	$selects = array();
	foreach ( $defs as $key => $def ) {
		if ( $def[0] && $def[3] ) {
			$selects[] = array(
				'name'     => 'cc_' . $key,
				'label'    => $def[2],
				'choices'  => $def[3],
				'selected' => (int) $def[1],
			);
		}
	}
	$is_filtered = $f['search'] || $f['from'] || $f['to'] || ( $show_t && $f['type'] ) || ( $show_d && $f['district'] ) || ( $show_s && $f['society'] );

	return cc_get_template( 'filters', array(
		'form_action' => $page,
		'hidden'      => $hidden,
		'f'           => $f,
		'selects'     => $selects,
		'is_filtered' => (bool) $is_filtered,
		'clear_url'   => add_query_arg( 'cc_view', $view, $page ), // Clearing filters keeps the view.
	) );
}

/** The month chosen with ?cc_month=YYYY-MM (by the calendar's Previous/Next links), or ''. */
function cc_requested_month() {
	$m = isset( $_GET['cc_month'] ) && is_string( $_GET['cc_month'] ) ? sanitize_text_field( wp_unslash( $_GET['cc_month'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	return preg_match( '/^(\d{4})-(0[1-9]|1[0-2])$/', $m ) ? $m : '';
}

/**
 * List view. It covers the same period the calendar view would show:
 *  1. the From / To filter dates, if set;
 *  2. otherwise the month being browsed (?cc_month), so switching views shows the same events;
 *  3. otherwise upcoming events from today, $days ahead (extendable with "Show more events").
 */
function cc_render_list( array $f, $days ) {
	$tz       = wp_timezone();
	$nav      = null;
	$note     = '';
	$more_url = '';
	$month    = cc_requested_month();

	if ( $f['from'] || $f['to'] ) {
		$from = new DateTimeImmutable( $f['from'] ? $f['from'] . ' 00:00:00' : 'today', $tz );
		$to   = $f['to'] ? new DateTimeImmutable( $f['to'] . ' 23:59:59', $tz ) : $from->modify( "+{$days} days" )->setTime( 23, 59, 59 );
		$max  = $from->modify( '+3 years' ); // Keep recurrence expansion bounded.
		if ( $to > $max ) {
			$to = $max;
		}
	} elseif ( $month ) {
		$from = new DateTimeImmutable( $month . '-01 00:00:00', $tz );
		$to   = $from->modify( 'last day of this month' )->setTime( 23, 59, 59 );
		$nav  = array(
			'prev_url' => add_query_arg( 'cc_month', $from->modify( '-1 month' )->format( 'Y-m' ) ),
			'next_url' => add_query_arg( 'cc_month', $from->modify( '+1 month' )->format( 'Y-m' ) ),
			'label'    => wp_date( 'F Y', $from->getTimestamp(), $tz ),
		);
	} else {
		$shown = isset( $_GET['cc_days'] ) && is_string( $_GET['cc_days'] ) ? absint( wp_unslash( $_GET['cc_days'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$shown = max( $days, min( 1095, $shown ) );
		$from  = new DateTimeImmutable( 'today', $tz );
		$to    = $from->modify( "+{$shown} days" )->setTime( 23, 59, 59 );
		if ( $shown < 1095 ) {
			$more_url = add_query_arg( 'cc_days', min( 1095, $shown + $days ) );
		}
		/* translators: %s: date */
		$note = sprintf( __( 'Showing upcoming events through %s.', 'community-calendar' ), wp_date( get_option( 'date_format' ), $to->getTimestamp(), $tz ) );
	}

	$occ = cc_query_occurrences( $from, $to, $f['district'], $f['society'], 500, $f['search'], $f['type'] );
	if ( ! $occ ) {
		$filtered = $f['type'] || $f['district'] || $f['society'] || $f['search'] || $f['from'] || $f['to'];
		return cc_get_template( 'event-list', array(
			'groups'        => array(),
			'empty_message' => $filtered ? __( 'No events match your filters.', 'community-calendar' ) : __( 'No events in this period.', 'community-calendar' ),
			'nav'           => $nav,
			'note'          => $note,
			'more_url'      => $more_url,
		) );
	}

	$groups  = array();
	$current = '';
	foreach ( array_slice( $occ, 0, 300 ) as $o ) {
		$day = $o['start']->format( 'Y-m-d' );
		if ( $day !== $current ) {
			$current  = $day;
			$groups[] = array(
				'heading' => wp_date( get_option( 'date_format' ), $o['start']->getTimestamp(), $tz ),
				'events'  => array(),
			);
		}
		$pid  = $o['post_id'];
		$meta = array_filter( array( cc_time_range( $o ), cc_meta( $pid, 'location' ), cc_meta( $pid, 'cost' ) ) );
		$tags = array_filter( array_merge( cc_event_type_labels( $pid ), array( cc_gf_label( 'district', $pid ), cc_gf_label( 'society', $pid ) ) ) );

		$groups[ count( $groups ) - 1 ]['events'][] = array(
			'url'   => get_permalink( $pid ),
			'title' => get_the_title( $pid ),
			'thumb' => get_the_post_thumbnail( $pid, 'thumbnail' ),
			'meta'  => implode( ' · ', $meta ),
			'tags'  => implode( ' · ', $tags ),
			'owner' => cc_owner_links( $pid ),
		);
	}
	return cc_get_template( 'event-list', array(
		'groups'        => $groups,
		'empty_message' => '',
		'nav'           => $nav,
		'note'          => $note,
		'more_url'      => $more_url,
	) );
}

function cc_render_month( array $f ) {
	$tz = wp_timezone();
	$m  = cc_requested_month();
	if ( '' === $m ) {
		$m = $f['from'] ? substr( $f['from'], 0, 7 ) : wp_date( 'Y-m', null, $tz );
	}
	$first      = new DateTimeImmutable( $m . '-01 00:00:00', $tz );
	$sow        = (int) get_option( 'start_of_week', 0 );
	$lead       = ( (int) $first->format( 'w' ) - $sow + 7 ) % 7;
	$dim        = (int) $first->format( 't' );
	$cells      = (int) ceil( ( $lead + $dim ) / 7 ) * 7;
	$grid_start = $first->modify( "-{$lead} days" );
	$grid_end   = $grid_start->modify( '+' . ( $cells - 1 ) . ' days' )->setTime( 23, 59, 59 );

	$by_day = array();
	$r_from = $f['from'] ? new DateTimeImmutable( $f['from'] . ' 00:00:00', $tz ) : null;
	$r_to   = $f['to'] ? new DateTimeImmutable( $f['to'] . ' 23:59:59', $tz ) : null;
	foreach ( cc_query_occurrences( $grid_start, $grid_end, $f['district'], $f['society'], 500, $f['search'], $f['type'] ) as $o ) {
		if ( ( $r_from && $o['end'] < $r_from ) || ( $r_to && $o['start'] > $r_to ) ) {
			continue;
		}
		$d    = $o['start']->setTime( 0, 0 );
		$last = $o['end']->setTime( 0, 0 );
		for ( $i = 0; $i < 62 && $d <= $last; $i++, $d = $d->modify( '+1 day' ) ) {
			$by_day[ $d->format( 'Y-m-d' ) ][] = $o;
		}
	}

	$today = wp_date( 'Y-m-d', null, $tz );
	$weeks = array();
	$row   = array();
	for ( $c = 0; $c < $cells; $c++ ) {
		$day   = $grid_start->modify( "+{$c} days" );
		$key   = $day->format( 'Y-m-d' );
		$class = array();
		if ( $day->format( 'Y-m' ) !== $m ) {
			$class[] = 'cc-other';
		}
		if ( $key === $today ) {
			$class[] = 'cc-today';
		}
		$items = isset( $by_day[ $key ] ) ? $by_day[ $key ] : array();
		$cell  = array(
			'number'  => (int) $day->format( 'j' ),
			'classes' => implode( ' ', $class ),
			'items'   => array(),
			'more'    => max( 0, count( $items ) - 3 ),
		);
		foreach ( array_slice( $items, 0, 3 ) as $o ) {
			$cell['items'][] = array(
				'url'   => get_permalink( $o['post_id'] ),
				'title' => get_the_title( $o['post_id'] ),
				'time'  => cc_time_range( $o ),
			);
		}
		$row[] = $cell;
		if ( 6 === $c % 7 ) {
			$weeks[] = $row;
			$row     = array();
		}
	}

	$utc    = new DateTimeZone( 'UTC' );
	$labels = array();
	for ( $i = 0; $i < 7; $i++ ) {
		$dow      = ( $sow + $i ) % 7; // 0 = Sunday. 1970-01-04 was a Sunday.
		$labels[] = wp_date( 'D', ( 3 + $dow ) * 86400 + 43200, $utc );
	}

	return cc_get_template( 'month-grid', array(
		'prev_url'       => add_query_arg( 'cc_month', $first->modify( '-1 month' )->format( 'Y-m' ) ),
		'next_url'       => add_query_arg( 'cc_month', $first->modify( '+1 month' )->format( 'Y-m' ) ),
		'month_label'    => wp_date( 'F Y', $first->getTimestamp(), $tz ),
		'weekday_labels' => $labels,
		'weeks'          => $weeks,
	) );
}

/* -------------------------------------------------------------------------
 * Single event page: photo, details, description, map
 * ---------------------------------------------------------------------- */

add_filter( 'the_content', 'cc_single_event_content' );

function cc_single_event_content( $content ) {
	if ( ! is_singular( 'cc_event' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	cc_enqueue_styles();
	$id   = get_the_ID();
	$tz   = wp_timezone();
	$rows = array();

	if ( cc_meta( $id, 'start' ) ) {
		$now  = new DateTimeImmutable( 'now', $tz );
		$next = cc_get_occurrences( $id, $now, $now->modify( '+5 years' ) );
		if ( $next ) {
			$occ = $next[0]; // Next upcoming occurrence.
		} else {
			$past = cc_get_occurrences( $id, new DateTimeImmutable( '@0' ), $now );
			$occ  = $past ? end( $past ) : null; // Most recent past occurrence.
		}
		if ( $occ ) {
			$rows[] = array(
				'label' => __( 'When', 'community-calendar' ),
				'type'  => 'text',
				'text'  => wp_date( get_option( 'date_format' ), $occ['start']->getTimestamp(), $tz ) . ', ' . cc_time_range( $occ ),
			);
		}
	}

	$freq = cc_meta( $id, 'freq' );
	if ( $freq ) {
		$n     = (int) cc_meta( $id, 'interval', 1 );
		$units = array(
			'daily'       => __( 'day', 'community-calendar' ),
			'weekly'      => __( 'week', 'community-calendar' ),
			'monthly'     => __( 'month', 'community-calendar' ),
			'monthly_nth' => __( 'month', 'community-calendar' ),
			'yearly'      => __( 'year', 'community-calendar' ),
		);
		/* translators: 1: number, 2: unit (day/week/month/year) */
		$text = $n > 1 ? sprintf( __( 'Every %1$d %2$ss', 'community-calendar' ), $n, $units[ $freq ] ) : sprintf( __( 'Every %s', 'community-calendar' ), $units[ $freq ] );
		if ( 'monthly_nth' === $freq ) {
			$labels = cc_nth_labels();
			$nth    = (int) cc_meta( $id, 'nth', 1 );
			/* translators: 1: ordinal (first, last…), 2: weekday name */
			$text .= ' ' . sprintf( __( 'on the %1$s %2$s', 'community-calendar' ), mb_strtolower( isset( $labels[ $nth ] ) ? $labels[ $nth ] : $labels[1] ), cc_weekday_full( (int) cc_meta( $id, 'nth_day', 1 ) ) );
		}
		if ( 'weekly' === $freq ) {
			$names = cc_weekday_names();
			$list  = array();
			foreach ( array_map( 'intval', (array) get_post_meta( $id, '_cc_weekdays', true ) ) as $d ) {
				if ( isset( $names[ $d ] ) ) {
					$list[] = $names[ $d ];
				}
			}
			if ( $list ) {
				$text .= ' (' . implode( ', ', $list ) . ')';
			}
		}
		$until = cc_meta( $id, 'until' );
		if ( $until ) {
			/* translators: %s: date */
			$text .= ' ' . sprintf( __( 'until %s', 'community-calendar' ), wp_date( get_option( 'date_format' ), strtotime( $until ), $tz ) );
		}
		$rows[] = array( 'label' => __( 'Repeats', 'community-calendar' ), 'type' => 'text', 'text' => $text );
	}

	$text_rows = array(
		__( 'Types', 'community-calendar' )    => implode( ', ', cc_event_type_labels( $id ) ),
		__( 'Location', 'community-calendar' ) => cc_meta( $id, 'location' ),
		__( 'Cost', 'community-calendar' )     => cc_meta( $id, 'cost' ),
		__( 'District', 'community-calendar' ) => cc_gf_label( 'district', $id ),
		__( 'Society', 'community-calendar' )  => cc_gf_label( 'society', $id ),
	);
	foreach ( $text_rows as $label => $value ) {
		if ( $value ) {
			$rows[] = array( 'label' => $label, 'type' => 'text', 'text' => $value );
		}
	}
	if ( $v = cc_meta( $id, 'website' ) ) {
		$rows[] = array( 'label' => __( 'Website', 'community-calendar' ), 'type' => 'link', 'url' => $v, 'text' => preg_replace( '#^https?://#', '', $v ) );
	}
	if ( $v = cc_meta( $id, 'email' ) ) {
		$rows[] = array( 'label' => __( 'Email', 'community-calendar' ), 'type' => 'email', 'text' => $v );
	}
	if ( $v = cc_meta( $id, 'phone' ) ) {
		$rows[] = array( 'label' => __( 'Telephone', 'community-calendar' ), 'type' => 'tel', 'text' => $v );
	}
	$rows[] = array(
		'label' => __( 'Add to calendar', 'community-calendar' ),
		'type'  => 'link',
		'url'   => cc_ical_url( array( 'cc_event' => $id ) ),
		'text'  => __( 'Download .ics', 'community-calendar' ),
	);

	return cc_get_template( 'single-event', array(
		'owner_links' => cc_owner_links( $id ),
		'image'       => cc_event_image_html( $id ),
		'rows'        => $rows,
		'content'     => $content,
		'map'         => cc_map_html( $id ),
	) );
}
