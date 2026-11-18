<?php
/**
 * Director role, capabilities and plugin activation.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Role slug that may manage its own events. Filterable: add_filter( 'cc_director_role', ... ). */
function cc_director_role() {
	return apply_filters( 'cc_director_role', 'director' );
}

function cc_director_caps() {
	return array(
		'read'                      => true,
		'upload_files'              => true,
		'edit_cc_events'            => true,
		'publish_cc_events'         => true,
		'edit_published_cc_events'  => true,
		'delete_cc_events'          => true,
		'delete_published_cc_events' => true,
	);
}

function cc_setup_roles() {
	$slug = cc_director_role();
	$role = get_role( $slug );
	if ( ! $role ) {
		$role = add_role( $slug, __( 'Director', 'community-calendar' ), array( 'read' => true ) );
	}
	if ( $role ) {
		foreach ( array_keys( cc_director_caps() ) as $cap ) {
			$role->add_cap( $cap );
		}
	}

	// Administrators can manage everyone's events. Directors do NOT get the "others" caps,
	// so they can only create/edit/delete events they authored.
	$admin = get_role( 'administrator' );
	if ( $admin ) {
		$all = array_merge(
			array_keys( cc_director_caps() ),
			array( 'edit_others_cc_events', 'delete_others_cc_events', 'read_private_cc_events', 'edit_private_cc_events', 'delete_private_cc_events' )
		);
		foreach ( $all as $cap ) {
			$admin->add_cap( $cap );
		}
	}
	update_option( 'cc_caps_version', CC_VERSION );
}

function cc_activate() {
	cc_register_post_type();
	cc_setup_roles();
	flush_rewrite_rules();
}

function cc_deactivate() {
	flush_rewrite_rules();
}

// Re-apply caps if the director role is created later or the plugin is updated.
add_action( 'init', function () {
	if ( get_option( 'cc_caps_version' ) !== CC_VERSION || ! get_role( cc_director_role() ) ) {
		cc_setup_roles();
	}
}, 1 );
