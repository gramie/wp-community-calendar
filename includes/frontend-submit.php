<?php
/**
 * Front-end event submission: [community_calendar_submit]. Directors add, edit and delete their own events here.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function cc_front_messages() {
	return array(
		'saved'        => __( 'Event saved.', 'community-calendar' ),
		'deleted'      => __( 'Event moved to the trash.', 'community-calendar' ),
		'image_failed' => __( 'The event was saved, but the image could not be uploaded.', 'community-calendar' ),
	);
}

add_action( 'template_redirect', 'cc_front_handle' );

function cc_front_handle() {
	if ( ! is_user_logged_in() || ! current_user_can( 'edit_cc_events' ) ) {
		return;
	}

	// phpcs:disable WordPress.Security.NonceVerification -- each branch verifies its own nonce.
	// Trash an event.
	if ( isset( $_GET['cc_delete'] ) ) {
		$id = absint( $_GET['cc_delete'] );
		if ( $id && isset( $_GET['_wpnonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'cc_delete_' . $id )
			&& 'cc_event' === get_post_type( $id )
			&& current_user_can( 'delete_post', $id ) ) {
			wp_trash_post( $id );
			wp_safe_redirect( add_query_arg( 'cc_msg', 'deleted', remove_query_arg( array( 'cc_delete', '_wpnonce', 'cc_edit', 'cc_msg' ) ) ) );
			exit;
		}
		return;
	}

	// Create / update an event.
	if ( empty( $_POST['cc_front_nonce'] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cc_front_nonce'] ) ), 'cc_front_submit' ) ) {
		return;
	}
	// phpcs:enable

	$post_id = isset( $_POST['cc_post_id'] ) ? absint( $_POST['cc_post_id'] ) : 0;
	if ( $post_id && ( 'cc_event' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) ) {
		wp_die( esc_html__( 'You are not allowed to edit this event.', 'community-calendar' ), '', array( 'response' => 403 ) );
	}

	$in      = isset( $_POST['cc'] ) && is_array( $_POST['cc'] ) ? wp_unslash( $_POST['cc'] ) : array();
	$title   = isset( $_POST['cc_title'] ) ? sanitize_text_field( wp_unslash( $_POST['cc_title'] ) ) : '';
	$content = isset( $_POST['cc_content'] ) ? wp_kses_post( wp_unslash( $_POST['cc_content'] ) ) : '';
	$errors  = array();

	if ( '' === $title ) {
		$errors[] = __( 'Please enter a title.', 'community-calendar' );
	}
	$errors = array_merge( $errors, cc_validate_event_input( $in ) );

	$has_image = ! empty( $_FILES['cc_image']['name'] );
	if ( $has_image ) {
		if ( ! current_user_can( 'upload_files' ) ) {
			$errors[] = __( 'You are not allowed to upload files.', 'community-calendar' );
		} elseif ( UPLOAD_ERR_OK !== (int) $_FILES['cc_image']['error'] ) {
			$errors[] = __( 'The image upload failed. It may be too large.', 'community-calendar' );
		} else {
			$check = wp_check_filetype_and_ext(
				$_FILES['cc_image']['tmp_name'],
				sanitize_file_name( wp_unslash( $_FILES['cc_image']['name'] ) ),
				array( 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp' )
			);
			if ( empty( $check['type'] ) ) {
				$errors[] = __( 'The image must be a JPG, PNG, GIF or WebP file.', 'community-calendar' );
			}
		}
	}

	if ( $errors ) {
		$GLOBALS['cc_front_state'] = compact( 'errors', 'in', 'title', 'content', 'post_id' );
		return; // Re-render the form with the entered values.
	}

	$data = array(
		'post_type'    => 'cc_event',
		'post_title'   => $title,
		'post_content' => $content,
	);
	if ( $post_id ) {
		$data['ID'] = $post_id;
		$result     = wp_update_post( wp_slash( $data ), true );
	} else {
		$data['post_status'] = apply_filters( 'cc_front_post_status', current_user_can( 'publish_cc_events' ) ? 'publish' : 'pending' );
		$data['post_author'] = get_current_user_id();
		$result              = wp_insert_post( wp_slash( $data ), true );
	}
	if ( is_wp_error( $result ) || ! $result ) {
		$errors[]                  = __( 'The event could not be saved. Please try again.', 'community-calendar' );
		$GLOBALS['cc_front_state'] = compact( 'errors', 'in', 'title', 'content', 'post_id' );
		return;
	}
	$post_id = (int) $result;
	cc_save_event_meta( $post_id, $in );

	$msg = 'saved';
	if ( ! empty( $_POST['cc_remove_image'] ) && ! $has_image ) {
		delete_post_thumbnail( $post_id );
	}
	if ( $has_image ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		$att = media_handle_upload( 'cc_image', $post_id );
		if ( is_wp_error( $att ) ) {
			$msg = 'image_failed';
		} else {
			set_post_thumbnail( $post_id, $att );
		}
	}

	$return = isset( $_POST['cc_return'] ) ? esc_url_raw( wp_unslash( $_POST['cc_return'] ) ) : '';
	wp_safe_redirect( add_query_arg( 'cc_msg', $msg, $return ? $return : home_url( '/' ) ) );
	exit;
}

add_shortcode( 'community_calendar_submit', 'cc_submit_shortcode' );

function cc_submit_shortcode() {
	cc_enqueue_styles();
	cc_enqueue_event_form_assets();
	$page = get_permalink( get_queried_object_id() );
	$page = $page ? $page : home_url( '/' );

	if ( ! is_user_logged_in() ) {
		return cc_get_template( 'submit-denied', array( 'reason' => 'login', 'login_url' => wp_login_url( $page ) ) );
	}
	if ( ! current_user_can( 'edit_cc_events' ) ) {
		return cc_get_template( 'submit-denied', array( 'reason' => 'role', 'login_url' => '' ) );
	}

	$msgs  = cc_front_messages();
	$state = isset( $GLOBALS['cc_front_state'] ) ? $GLOBALS['cc_front_state'] : null;

	// phpcs:disable WordPress.Security.NonceVerification
	$msg_key = isset( $_GET['cc_msg'] ) && is_string( $_GET['cc_msg'] ) ? sanitize_key( wp_unslash( $_GET['cc_msg'] ) ) : '';
	$notice  = ( $msg_key && isset( $msgs[ $msg_key ] ) ) ? $msgs[ $msg_key ] : '';
	$edit_id = $state ? (int) $state['post_id'] : ( isset( $_GET['cc_edit'] ) ? absint( $_GET['cc_edit'] ) : 0 );
	// phpcs:enable

	$edit_post  = null;
	$edit_error = '';
	if ( $edit_id ) {
		$p = get_post( $edit_id );
		if ( $p && 'cc_event' === $p->post_type && current_user_can( 'edit_post', $edit_id ) ) {
			$edit_post = $p;
		} else {
			$edit_error = __( 'You cannot edit that event.', 'community-calendar' );
			$edit_id    = 0;
		}
	}

	// Form values: what was just submitted (after an error), else the event being edited.
	$title   = $state ? $state['title'] : ( $edit_post ? $edit_post->post_title : '' );
	$content = $state ? $state['content'] : ( $edit_post ? $edit_post->post_content : '' );
	if ( $state ) {
		$in  = $state['in'];
		$pre = array();
		foreach ( array( 'start', 'end', 'freq', 'interval', 'until', 'location', 'cost', 'website', 'email', 'phone' ) as $k ) {
			$pre[ $k ] = isset( $in[ $k ] ) && is_scalar( $in[ $k ] ) ? (string) $in[ $k ] : '';
		}
		$pre['district_id']    = isset( $in['district'] ) ? (int) $in['district'] : 0;
		$pre['society_id']     = isset( $in['society'] ) ? (int) $in['society'] : 0;
		$pre['type_ids']       = isset( $in['types'] ) ? array_map( 'intval', (array) $in['types'] ) : array();
		$pre['weekdays']       = isset( $in['weekdays'] ) ? array_map( 'intval', (array) $in['weekdays'] ) : array();
		$pre['nth']            = isset( $in['nth'] ) ? (int) $in['nth'] : 1;
		$pre['nth_day']        = isset( $in['nth_day'] ) ? (int) $in['nth_day'] : 1;
		$GLOBALS['cc_prefill'] = $pre;
	}
	$fields = cc_get_meta_box_html( $edit_id, true );
	unset( $GLOBALS['cc_prefill'] );

	$form = cc_get_template( 'submit-form', array(
		'heading'    => $edit_post ? __( 'Edit event', 'community-calendar' ) : __( 'Add an event', 'community-calendar' ),
		'button'     => $edit_post ? __( 'Update event', 'community-calendar' ) : __( 'Submit event', 'community-calendar' ),
		'page'       => $page,
		'edit_id'    => $edit_id,
		'title'      => $title,
		'content'    => $content,
		'fields'     => $fields,
		'image_html' => ( $edit_post && has_post_thumbnail( $edit_post ) ) ? get_the_post_thumbnail( $edit_post, 'thumbnail' ) : '',
		'max_size'   => size_format( wp_max_upload_size() ),
		'cancel_url' => $edit_post ? remove_query_arg( array( 'cc_edit', 'cc_msg', 'cc_delete', '_wpnonce' ), $page ) : '',
	) );

	return cc_get_template( 'submit-page', array(
		'notice'     => $notice,
		'errors'     => ( $state && $state['errors'] ) ? $state['errors'] : array(),
		'edit_error' => $edit_error,
		'my_events'  => cc_front_my_events(),
		'form'       => $form,
	) );
}

function cc_front_my_events() {
	$q = new WP_Query( array(
		'post_type'      => 'cc_event',
		'author'         => get_current_user_id(),
		'post_status'    => array( 'publish', 'pending', 'draft', 'future', 'private' ),
		'posts_per_page' => 50,
		'no_found_rows'  => true,
	) );
	if ( ! $q->have_posts() ) {
		return '';
	}
	$base = remove_query_arg( array( 'cc_msg', 'cc_delete', '_wpnonce', 'cc_edit' ) );
	$tz   = wp_timezone();
	$rows = array();
	foreach ( $q->posts as $p ) {
		$s      = cc_meta( $p->ID, 'start' );
		$rows[] = array(
			'title'      => get_the_title( $p ),
			'when'       => $s ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), ( new DateTimeImmutable( $s, $tz ) )->getTimestamp(), $tz ) : '—',
			'status'     => get_post_status_object( $p->post_status )->label,
			'edit_url'   => current_user_can( 'edit_post', $p->ID ) ? add_query_arg( 'cc_edit', $p->ID, $base ) : '',
			'view_url'   => 'publish' === $p->post_status ? get_permalink( $p ) : '',
			'delete_url' => current_user_can( 'delete_post', $p->ID ) ? wp_nonce_url( add_query_arg( 'cc_delete', $p->ID, $base ), 'cc_delete_' . $p->ID ) : '',
		);
	}
	return cc_get_template( 'my-events', array( 'rows' => $rows ) );
}
