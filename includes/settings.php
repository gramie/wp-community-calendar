<?php
/**
 * Settings > Community Calendar.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', function () {
	add_options_page(
		__( 'Community Calendar', 'community-calendar' ),
		__( 'Community Calendar', 'community-calendar' ),
		'manage_options',
		'cc-settings',
		'cc_render_settings'
	);
} );

add_action( 'admin_init', function () {
	foreach ( array( 'district', 'society' ) as $type ) {
		register_setting( 'cc_settings', "cc_{$type}_form", array( 'sanitize_callback' => 'absint' ) );
		register_setting( 'cc_settings', "cc_{$type}_field", array( 'sanitize_callback' => 'sanitize_text_field' ) );
	}
	register_setting( 'cc_settings', 'cc_geo_suffix', array( 'sanitize_callback' => 'sanitize_text_field' ) );
	register_setting( 'cc_settings', 'cc_submit_page', array( 'sanitize_callback' => 'absint' ) );
} );

function cc_render_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	echo cc_get_template( 'settings-page', array( // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the template.
		'gf_active' => class_exists( 'GFAPI' ),
		'forms'     => class_exists( 'GFAPI' ) ? GFAPI::get_forms() : array(),
		'types'     => array(
			'district' => __( 'Districts', 'community-calendar' ),
			'society'  => __( 'Societies', 'community-calendar' ),
		),
	) );
}
