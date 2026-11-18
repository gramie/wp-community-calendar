<?php
/**
 * Shared helpers: event meta, labels, date formatting, template loader and assets.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function cc_meta( $post_id, $key, $default = '' ) {
	if ( isset( $GLOBALS['cc_prefill'] ) && array_key_exists( $key, $GLOBALS['cc_prefill'] ) ) {
		return $GLOBALS['cc_prefill'][ $key ];
	}
	$v = get_post_meta( $post_id, '_cc_' . $key, true );
	return ( '' === $v || false === $v ) ? $default : $v;
}

function cc_to_input( $mysql ) {
	return $mysql ? str_replace( ' ', 'T', substr( $mysql, 0, 16 ) ) : '';
}

function cc_parse_local( $value ) {
	$d = DateTime::createFromFormat( 'Y-m-d\TH:i', trim( (string) $value ), wp_timezone() );
	return $d ?: null;
}

function cc_weekday_names() {
	return array(
		1 => __( 'Mon', 'community-calendar' ),
		2 => __( 'Tue', 'community-calendar' ),
		3 => __( 'Wed', 'community-calendar' ),
		4 => __( 'Thu', 'community-calendar' ),
		5 => __( 'Fri', 'community-calendar' ),
		6 => __( 'Sat', 'community-calendar' ),
		7 => __( 'Sun', 'community-calendar' ),
	);
}

function cc_nth_labels() {
	return array(
		1  => __( 'First', 'community-calendar' ),
		2  => __( 'Second', 'community-calendar' ),
		3  => __( 'Third', 'community-calendar' ),
		4  => __( 'Fourth', 'community-calendar' ),
		-1 => __( 'Last', 'community-calendar' ),
	);
}

/** Full weekday name for an ISO day number (1 = Monday … 7 = Sunday). */
function cc_weekday_full( $n ) {
	$n = max( 1, min( 7, (int) $n ) );
	// 1970-01-04 was a Sunday; noon UTC keeps the day stable in any timezone.
	return wp_date( 'l', ( 3 + ( $n % 7 ) ) * 86400 + 43200, new DateTimeZone( 'UTC' ) );
}

function cc_freq_label( $freq ) {
	$labels = array(
		'daily'       => __( 'Daily', 'community-calendar' ),
		'weekly'      => __( 'Weekly', 'community-calendar' ),
		'monthly'     => __( 'Monthly', 'community-calendar' ),
		'monthly_nth' => __( 'Monthly (nth weekday)', 'community-calendar' ),
		'yearly'      => __( 'Yearly', 'community-calendar' ),
	);
	return isset( $labels[ $freq ] ) ? $labels[ $freq ] : $freq;
}

function cc_time_range( $occ ) {
	$tf    = get_option( 'time_format' );
	$df    = get_option( 'date_format' );
	$tz    = wp_timezone();
	$s     = $occ['start'];
	$e     = $occ['end'];
	$s_ts  = $s->getTimestamp();
	$e_ts  = $e->getTimestamp();
	if ( $s->format( 'Y-m-d' ) === $e->format( 'Y-m-d' ) ) {
		return wp_date( $tf, $s_ts, $tz ) . ( $e_ts > $s_ts ? ' – ' . wp_date( $tf, $e_ts, $tz ) : '' );
	}
	return wp_date( $df . ' ' . $tf, $s_ts, $tz ) . ' – ' . wp_date( $df . ' ' . $tf, $e_ts, $tz );
}


/* -------------------------------------------------------------------------
 * Templates
 *
 * HTML lives in templates/*.php. A theme can override any of them by copying
 * the file to  your-theme/community-calendar/<same name>.php
 * ---------------------------------------------------------------------- */

/** Path of a template: the theme's override if present, else the plugin's own. */
function cc_locate_template( $name ) {
	$name = preg_replace( '/[^a-z0-9_\/-]/i', '', (string) $name );
	$file = locate_template( 'community-calendar/' . $name . '.php' );
	if ( ! $file ) {
		$file = CC_DIR . 'templates/' . $name . '.php';
	}
	return apply_filters( 'cc_template_file', $file, $name );
}

/** Renders a template with the given variables and returns the HTML. */
function cc_get_template( $name, array $args = array() ) {
	$file = cc_locate_template( $name );
	if ( ! is_readable( $file ) ) {
		return '';
	}
	ob_start();
	( static function ( $cc_template_file, $cc_template_args ) {
		extract( $cc_template_args, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract -- variables are documented at the top of each template.
		include $cc_template_file;
	} )( $file, $args );
	return ob_get_clean();
}

/* -------------------------------------------------------------------------
 * Assets (assets/css, assets/js)
 * ---------------------------------------------------------------------- */

function cc_register_assets() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;

	wp_register_style( 'cc-calendar', CC_URL . 'assets/css/community-calendar.css', array(), CC_VERSION );
	wp_register_style( 'cc-event-form', CC_URL . 'assets/css/event-form.css', array(), CC_VERSION );
	wp_register_script( 'cc-calendar', CC_URL . 'assets/js/calendar.js', array(), CC_VERSION, true );
	wp_register_script( 'cc-event-form', CC_URL . 'assets/js/event-form.js', array(), CC_VERSION, true );
	wp_localize_script( 'cc-event-form', 'ccEventForm', array(
		'units'  => array(
			'daily'       => __( 'day(s)', 'community-calendar' ),
			'weekly'      => __( 'week(s)', 'community-calendar' ),
			'monthly'     => __( 'month(s)', 'community-calendar' ),
			'monthly_nth' => __( 'month(s)', 'community-calendar' ),
			'yearly'      => __( 'year(s)', 'community-calendar' ),
		),
		'badEnd' => __( 'The event cannot end before it starts.', 'community-calendar' ),
	) );
}
add_action( 'wp_enqueue_scripts', 'cc_register_assets' );
add_action( 'admin_enqueue_scripts', 'cc_register_assets' );

/** Public calendar, event pages and submission page styles/scripts. */
function cc_enqueue_styles() {
	cc_register_assets();
	wp_enqueue_style( 'cc-calendar' );
	wp_enqueue_script( 'cc-calendar' );
}

/** Event fields (admin meta box and front-end form) styles/scripts. */
function cc_enqueue_event_form_assets() {
	cc_register_assets();
	wp_enqueue_style( 'cc-event-form' );
	wp_enqueue_script( 'cc-event-form' );
}
