<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get logs for a task.
 *
 * @param int $task_id Task ID.
 * @return array
 */
function tf_get_task_logs( $task_id ) {
	$logs = get_post_meta( $task_id, '_tf_logs', true );
	return is_array( $logs ) ? $logs : array();
}

/**
 * Append a log entry to a task.
 *
 * @param int   $task_id Task ID.
 * @param array $log Log data.
 */
function tf_add_task_log( $task_id, $log ) {
	$logs   = tf_get_task_logs( $task_id );
	$logs[] = array(
		'task_id'    => (int) $task_id,
		'start_time' => isset( $log['start_time'] ) ? (int) $log['start_time'] : 0,
		'end_time'   => isset( $log['end_time'] ) ? (int) $log['end_time'] : 0,
		'duration'   => isset( $log['duration'] ) ? (int) $log['duration'] : 0,
		'type'       => isset( $log['type'] ) ? sanitize_text_field( $log['type'] ) : 'manual',
	);

	update_post_meta( $task_id, '_tf_logs', $logs );
}

/**
 * Add seconds to task total.
 *
 * @param int $task_id Task ID.
 * @param int $seconds Seconds to add.
 * @return int
 */
function tf_add_time_to_total( $task_id, $seconds ) {
	$current = (int) get_post_meta( $task_id, '_tf_total_seconds', true );
	$new     = max( 0, $current + (int) $seconds );
	update_post_meta( $task_id, '_tf_total_seconds', $new );

	return $new;
}

/**
 * Start timer for task. Only one active timer globally.
 *
 * @param int $task_id Task ID.
 * @return array|WP_Error
 */
function tf_start_timer( $task_id ) {
	$active_task = (int) get_option( 'tf_active_task', 0 );

	if ( $active_task > 0 ) {
		return new WP_Error( 'tf_timer_exists', __( 'A timer is already running for another task.', 'timeflow-prototype-1' ) );
	}

	$task = get_post( $task_id );
	if ( ! $task || 'tf_task' !== $task->post_type ) {
		return new WP_Error( 'tf_invalid_task', __( 'Invalid task.', 'timeflow-prototype-1' ) );
	}

	$start_time = current_time( 'timestamp' );
	update_option( 'tf_active_task', (int) $task_id, false );
	update_option( 'tf_timer_start', (int) $start_time, false );

	return array(
		'task_id'    => (int) $task_id,
		'start_time' => (int) $start_time,
	);
}

/**
 * Stop active timer.
 *
 * @param int $task_id Task ID.
 * @return array|WP_Error
 */
function tf_stop_timer( $task_id ) {
	$active_task = (int) get_option( 'tf_active_task', 0 );
	$start_time  = (int) get_option( 'tf_timer_start', 0 );

	if ( $active_task <= 0 || $start_time <= 0 ) {
		return new WP_Error( 'tf_no_timer', __( 'No active timer is running.', 'timeflow-prototype-1' ) );
	}

	if ( (int) $task_id !== $active_task ) {
		return new WP_Error( 'tf_task_mismatch', __( 'Active timer belongs to another task.', 'timeflow-prototype-1' ) );
	}

	$end_time = current_time( 'timestamp' );
	$duration = max( 1, $end_time - $start_time );

	tf_add_task_log(
		$task_id,
		array(
			'task_id'    => $task_id,
			'start_time' => $start_time,
			'end_time'   => $end_time,
			'duration'   => $duration,
			'type'       => 'timer',
		)
	);

	$total = tf_add_time_to_total( $task_id, $duration );

	update_option( 'tf_active_task', 0, false );
	update_option( 'tf_timer_start', 0, false );

	return array(
		'duration'    => $duration,
		'total_time'  => $total,
		'formatted'   => tf_format_duration( $total ),
		'ended_at'    => $end_time,
		'started_at'  => $start_time,
	);
}

/**
 * Add manual minutes as time log.
 *
 * @param int $task_id Task ID.
 * @param int $minutes Minutes.
 * @return array|WP_Error
 */
function tf_add_manual_time( $task_id, $minutes ) {
	$task = get_post( $task_id );
	if ( ! $task || 'tf_task' !== $task->post_type ) {
		return new WP_Error( 'tf_invalid_task', __( 'Invalid task.', 'timeflow-prototype-1' ) );
	}

	$minutes = (int) $minutes;
	if ( $minutes <= 0 ) {
		return new WP_Error( 'tf_invalid_time', __( 'Minutes must be greater than zero.', 'timeflow-prototype-1' ) );
	}

	$duration = $minutes * 60;
	$now      = current_time( 'timestamp' );

	tf_add_task_log(
		$task_id,
		array(
			'task_id'    => $task_id,
			'start_time' => $now,
			'end_time'   => $now,
			'duration'   => $duration,
			'type'       => 'manual',
		)
	);

	$total = tf_add_time_to_total( $task_id, $duration );

	return array(
		'duration'   => $duration,
		'total_time' => $total,
		'formatted'  => tf_format_duration( $total ),
		'logged_at'  => $now,
	);
}

/**
 * Format seconds to h m s.
 *
 * @param int $seconds Seconds.
 * @return string
 */
function tf_format_duration( $seconds ) {
	$seconds = max( 0, (int) $seconds );
	$hours   = floor( $seconds / 3600 );
	$minutes = floor( ( $seconds % 3600 ) / 60 );
	$remain  = $seconds % 60;

	if ( $hours > 0 ) {
		return sprintf( '%dh %dm %ds', $hours, $minutes, $remain );
	}

	if ( $minutes > 0 ) {
		return sprintf( '%dm %ds', $minutes, $remain );
	}

	return sprintf( '%ds', $remain );
}

/**
 * Format timestamp to localized datetime.
 *
 * @param int $timestamp Timestamp.
 * @return string
 */
function tf_format_datetime( $timestamp ) {
	$timestamp = (int) $timestamp;
	if ( $timestamp <= 0 ) {
		return '—';
	}

	return wp_date( 'Y-m-d H:i:s', $timestamp );
}
