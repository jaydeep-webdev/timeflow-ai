<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validate shared AJAX access.
 */
function tf_ajax_validate_request() {
	check_ajax_referer( 'tf_timer_nonce', 'nonce' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'timeflow-ai-1-1' ) ), 403 );
	}
}

/**
 * START_TIMER action.
 */
function tf_ajax_start_timer() {
	tf_ajax_validate_request();

	$task_id = isset( $_POST['task_id'] ) ? absint( $_POST['task_id'] ) : 0;
	$result  = tf_start_timer( $task_id );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	wp_send_json_success(
		array(
			'message' => __( 'Timer started.', 'timeflow-ai-1-1' ),
			'data'    => $result,
		)
	);
}
add_action( 'wp_ajax_tf_start_timer', 'tf_ajax_start_timer' );

/**
 * STOP_TIMER action.
 */
function tf_ajax_stop_timer() {
	tf_ajax_validate_request();

	$result = tf_stop_timer();

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	wp_send_json_success(
		array(
			'message' => __( 'Timer stopped.', 'timeflow-ai-1-1' ),
			'data'    => $result,
		)
	);
}
add_action( 'wp_ajax_tf_stop_timer', 'tf_ajax_stop_timer' );

/**
 * ADD_TIME action.
 */
function tf_ajax_add_manual_time() {
	tf_ajax_validate_request();

	$task_id = isset( $_POST['task_id'] ) ? absint( $_POST['task_id'] ) : 0;
	$minutes = isset( $_POST['minutes'] ) ? absint( $_POST['minutes'] ) : 0;
	$result  = tf_add_manual_time( $task_id, $minutes );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	wp_send_json_success(
		array(
			'message' => __( 'Manual time added.', 'timeflow-ai-1-1' ),
			'data'    => $result,
		)
	);
}
add_action( 'wp_ajax_tf_add_manual_time', 'tf_ajax_add_manual_time' );

/**
 * FETCH_DASHBOARD action.
 */
function tf_ajax_fetch_dashboard() {
	tf_ajax_validate_request();
	wp_send_json_success( tf_build_dashboard_data() );
}
add_action( 'wp_ajax_tf_fetch_dashboard', 'tf_ajax_fetch_dashboard' );
