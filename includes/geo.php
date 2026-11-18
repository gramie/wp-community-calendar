<?php
/**
 * Event photo and OpenStreetMap map for single event pages (geocoding via Nominatim).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The event page shows the photo itself (below), so stop the theme from printing it a second time.
add_filter( 'post_thumbnail_html', function ( $html, $post_id ) {
	if ( is_singular( 'cc_event' ) && in_the_loop() && is_main_query()
		&& (int) $post_id === (int) get_queried_object_id()
		&& apply_filters( 'cc_hide_theme_featured_image', true ) ) {
		return '';
	}
	return $html;
}, 10, 2 );

function cc_event_image_html( $post_id ) {
	$thumb_id = get_post_thumbnail_id( $post_id );
	if ( ! $thumb_id ) {
		return '';
	}
	$attr = array( 'class' => 'cc-event-image' );
	if ( '' === trim( (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ) ) ) {
		$attr['alt'] = get_the_title( $post_id );
	}
	$img = wp_get_attachment_image( $thumb_id, 'large', false, $attr ); // Bypasses the post_thumbnail_html filter above.
	return $img ? cc_get_template( 'event-image', array( 'image_html' => $img ) ) : '';
}

/** Looks a place up with OpenStreetMap's Nominatim service. Returns array( lat, lng ) or null. */
function cc_geocode( $query ) {
	$query = apply_filters( 'cc_geocode_query', $query );
	$url   = add_query_arg( array(
		'format' => 'jsonv2',
		'limit'  => 1,
		'q'      => $query,
	), 'https://nominatim.openstreetmap.org/search' );
	// Nominatim's usage policy requires an identifying User-Agent and at most one request per second.
	$res = wp_remote_get( $url, array(
		'timeout'    => 8,
		'user-agent' => 'CommunityCalendar/' . CC_VERSION . ' (' . home_url() . ')',
	) );
	if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
		return null;
	}
	$data = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( empty( $data[0]['lat'] ) || empty( $data[0]['lon'] ) ) {
		return null;
	}
	$lat = (float) $data[0]['lat'];
	$lng = (float) $data[0]['lon'];
	if ( $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 ) {
		return null;
	}
	return array( $lat, $lng );
}

/** Stores coordinates for an event's location; only looks again when the location (or search area) changed. */
function cc_update_geocode( $post_id, $location ) {
	if ( '' === $location ) {
		delete_post_meta( $post_id, '_cc_lat' );
		delete_post_meta( $post_id, '_cc_lng' );
		delete_post_meta( $post_id, '_cc_geo_q' );
		return;
	}
	$suffix = trim( (string) get_option( 'cc_geo_suffix', '' ) );
	$query  = '' !== $suffix ? $location . ', ' . $suffix : $location;
	if ( get_post_meta( $post_id, '_cc_geo_q', true ) === $query && '' !== (string) get_post_meta( $post_id, '_cc_lat', true ) ) {
		return;
	}
	$found = cc_geocode( $query );
	update_post_meta( $post_id, '_cc_geo_q', $query );
	if ( $found ) {
		update_post_meta( $post_id, '_cc_lat', $found[0] );
		update_post_meta( $post_id, '_cc_lng', $found[1] );
	} else {
		delete_post_meta( $post_id, '_cc_lat' );
		delete_post_meta( $post_id, '_cc_lng' );
	}
}

function cc_map_html( $post_id ) {
	$location = cc_meta( $post_id, 'location' );
	if ( '' === (string) $location ) {
		return '';
	}
	$lat = get_post_meta( $post_id, '_cc_lat', true );
	$lng = get_post_meta( $post_id, '_cc_lng', true );

	if ( '' === (string) $lat || '' === (string) $lng ) {
		// Couldn't be located automatically: link to an OpenStreetMap search instead.
		$suffix = trim( (string) get_option( 'cc_geo_suffix', '' ) );
		return cc_get_template( 'map', array(
			'mode'       => 'search',
			'search_url' => add_query_arg( 'query', '' !== $suffix ? $location . ', ' . $suffix : $location, 'https://www.openstreetmap.org/search' ),
			'embed_url'  => '',
			'large_url'  => '',
			'title'      => '',
		) );
	}

	$lat  = (float) $lat;
	$lng  = (float) $lng;
	$dlat = 0.004;
	$dlng = 0.008 / max( 0.2, cos( deg2rad( $lat ) ) );
	$fmt  = function ( $n ) {
		return number_format( $n, 6, '.', '' );
	};
	$bbox = implode( ',', array_map( $fmt, array( $lng - $dlng, $lat - $dlat, $lng + $dlng, $lat + $dlat ) ) );

	return cc_get_template( 'map', array(
		'mode'       => 'embed',
		'search_url' => '',
		'embed_url'  => 'https://www.openstreetmap.org/export/embed.html?bbox=' . rawurlencode( $bbox ) . '&layer=mapnik&marker=' . rawurlencode( $fmt( $lat ) . ',' . $fmt( $lng ) ),
		'large_url'  => 'https://www.openstreetmap.org/?mlat=' . $fmt( $lat ) . '&mlon=' . $fmt( $lng ) . '#map=16/' . $fmt( $lat ) . '/' . $fmt( $lng ),
		/* translators: %s: event location */
		'title'      => sprintf( __( 'Map of %s', 'community-calendar' ), $location ),
	) );
}
