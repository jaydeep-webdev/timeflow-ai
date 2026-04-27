<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check common ajax permissions.
 */
function tf_ajax_validate_request() {
	check_ajax_referer( 'tf_timer_nonce', 'nonce' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'Permission denied.', 'timeflow-prototype-1' ),
			),
			403
		);
	}
}

/**
 * Start timer AJAX handler.
 */
function tf_ajax_start_timer() {
	tf_ajax_validate_request();

	$task_id = isset( $_POST['task_id'] ) ? absint( $_POST['task_id'] ) : 0;
	$result  = tf_start_timer( $task_id );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error(
			array(
				'message' => $result->get_error_message(),
			)
		);
	}

	wp_send_json_success(
		array(
			'message' => __( 'Timer started.', 'timeflow-prototype-1' ),
			'data'    => $result,
		)
	);
}
add_action( 'wp_ajax_tf_start_timer', 'tf_ajax_start_timer' );

/**
 * Stop timer AJAX handler.
 */
function tf_ajax_stop_timer() {
	tf_ajax_validate_request();

	$task_id = isset( $_POST['task_id'] ) ? absint( $_POST['task_id'] ) : 0;
	$result  = tf_stop_timer( $task_id );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error(
			array(
				'message' => $result->get_error_message(),
			)
		);
	}

	wp_send_json_success(
		array(
			'message' => __( 'Timer stopped and logged.', 'timeflow-prototype-1' ),
			'data'    => $result,
		)
	);
}
add_action( 'wp_ajax_tf_stop_timer', 'tf_ajax_stop_timer' );

/**
 * Add manual time AJAX handler.
 */
function tf_ajax_add_manual_time() {
	tf_ajax_validate_request();

	$task_id = isset( $_POST['task_id'] ) ? absint( $_POST['task_id'] ) : 0;
	$minutes = isset( $_POST['minutes'] ) ? absint( $_POST['minutes'] ) : 0;
	$result  = tf_add_manual_time( $task_id, $minutes );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error(
			array(
				'message' => $result->get_error_message(),
			)
		);
	}

	wp_send_json_success(
		array(
			'message' => __( 'Manual time added.', 'timeflow-prototype-1' ),
			'data'    => $result,
		)
	);
}
add_action( 'wp_ajax_tf_add_manual_time', 'tf_ajax_add_manual_time' );
