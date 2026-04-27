<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get task logs.
 *
 * @param int $task_id Task ID.
 * @return array
 */
function tf_get_task_logs( $task_id ) {
	$logs = get_post_meta( $task_id, '_tf_logs', true );
	return is_array( $logs ) ? $logs : array();
}

/**
 * Add log to task.
 *
 * @param int   $task_id Task ID.
 * @param array $log Log data.
 */
function tf_add_task_log( $task_id, $log ) {
	$logs   = tf_get_task_logs( $task_id );
	$logs[] = array(
		'task_id'    => (int) $task_id,
		'project_id' => isset( $log['project_id'] ) ? (int) $log['project_id'] : 0,
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
 * @param int $seconds Seconds.
 * @return int
 */
function tf_add_time_to_task_total( $task_id, $seconds ) {
	$current = (int) get_post_meta( $task_id, 'total_time', true );
	if ( $current <= 0 ) {
		$current = (int) get_post_meta( $task_id, '_tf_total_seconds', true );
	}
	$new = max( 0, $current + (int) $seconds );
	update_post_meta( $task_id, 'total_time', $new );
	update_post_meta( $task_id, '_tf_total_seconds', $new );

	return $new;
}

/**
 * Add seconds to project total.
 *
 * @param int $project_id Project ID.
 * @param int $seconds Seconds.
 * @return int
 */
function tf_add_time_to_project_total( $project_id, $seconds ) {
	$project_id = absint( $project_id );
	if ( $project_id <= 0 ) {
		return 0;
	}

	$current = (int) get_post_meta( $project_id, 'total_time', true );
	if ( $current <= 0 ) {
		$current = (int) get_post_meta( $project_id, '_tf_total_seconds', true );
	}
	$new = max( 0, $current + (int) $seconds );
	update_post_meta( $project_id, 'total_time', $new );
	update_post_meta( $project_id, '_tf_total_seconds', $new );

	return $new;
}

/**
 * Start timer globally.
 *
 * @param int $task_id Task ID.
 * @return array|WP_Error
 */
function tf_start_timer( $task_id ) {
	$task_id      = absint( $task_id );
	$active_task  = (int) get_option( 'tf_active_task', 0 );
	$active_start = (int) get_option( 'tf_timer_start', 0 );

	if ( $task_id <= 0 ) {
		return new WP_Error( 'tf_invalid_selection', __( 'Please select a project and task', 'timeflow-ai-1-1' ) );
	}

	if ( $active_task > 0 && $active_start > 0 ) {
		return new WP_Error( 'tf_timer_exists', __( 'Timer already running', 'timeflow-ai-1-1' ) );
	}

	$task = get_post( $task_id );
	if ( ! $task || 'tf_task' !== $task->post_type ) {
		return new WP_Error( 'tf_invalid_task', __( 'Invalid task.', 'timeflow-ai-1-1' ) );
	}

	$project_id = tf_get_task_project_id( $task_id );
	$start_time = time();

	update_option( 'tf_active_task', $task_id, false );
	update_option( 'tf_active_project', max( 0, $project_id ), false );
	update_option( 'tf_timer_start', $start_time, false );

	return array(
		'task_id'    => $task_id,
		'project_id' => $project_id,
		'start_time' => $start_time,
	);
}

/**
 * Stop timer globally.
 *
 * @return array|WP_Error
 */
function tf_stop_timer() {
	$task_id    = (int) get_option( 'tf_active_task', 0 );
	$project_id = (int) get_option( 'tf_active_project', 0 );
	$start_time = (int) get_option( 'tf_timer_start', 0 );

	if ( $task_id <= 0 || $start_time <= 0 ) {
		return new WP_Error( 'tf_no_timer', __( 'No active timer is running.', 'timeflow-ai-1-1' ) );
	}

	$end_time = time();
	$duration = max( 1, $end_time - $start_time );

	tf_add_task_log(
		$task_id,
		array(
			'task_id'    => $task_id,
			'project_id' => $project_id,
			'start_time' => $start_time,
			'end_time'   => $end_time,
			'duration'   => $duration,
			'type'       => 'timer',
		)
	);

	$task_total    = tf_add_time_to_task_total( $task_id, $duration );
	$project_total = $project_id > 0 ? tf_add_time_to_project_total( $project_id, $duration ) : 0;

	update_option( 'tf_active_task', 0, false );
	update_option( 'tf_active_project', 0, false );
	update_option( 'tf_timer_start', 0, false );

	return array(
		'task_id'         => $task_id,
		'project_id'      => $project_id,
		'duration'        => $duration,
		'task_total'      => $task_total,
		'project_total'   => $project_total,
		'formatted_total' => tf_format_duration( $task_total ),
		'start_time'      => $start_time,
		'end_time'        => $end_time,
	);
}

/**
 * Add manual minutes.
 *
 * @param int $task_id Task ID.
 * @param int $minutes Minutes.
 * @return array|WP_Error
 */
function tf_add_manual_time( $task_id, $minutes ) {
	$task_id = absint( $task_id );
	$minutes = absint( $minutes );

	if ( $task_id <= 0 ) {
		return new WP_Error( 'tf_invalid_selection', __( 'Please select a project and task', 'timeflow-ai-1-1' ) );
	}

	if ( $minutes <= 0 ) {
		return new WP_Error( 'tf_invalid_minutes', __( 'Minutes must be greater than zero.', 'timeflow-ai-1-1' ) );
	}

	$task = get_post( $task_id );
	if ( ! $task || 'tf_task' !== $task->post_type ) {
		return new WP_Error( 'tf_invalid_task', __( 'Invalid task.', 'timeflow-ai-1-1' ) );
	}

	$project_id = tf_get_task_project_id( $task_id );
	$duration   = $minutes * 60;
	$now        = time();

	tf_add_task_log(
		$task_id,
		array(
			'task_id'    => $task_id,
			'project_id' => $project_id,
			'start_time' => $now,
			'end_time'   => $now,
			'duration'   => $duration,
			'type'       => 'manual',
		)
	);

	$task_total    = tf_add_time_to_task_total( $task_id, $duration );
	$project_total = $project_id > 0 ? tf_add_time_to_project_total( $project_id, $duration ) : 0;

	return array(
		'task_id'         => $task_id,
		'project_id'      => $project_id,
		'duration'        => $duration,
		'task_total'      => $task_total,
		'project_total'   => $project_total,
		'formatted_total' => tf_format_duration( $task_total ),
		'logged_at'       => $now,
	);
}

/**
 * Get all task logs flattened.
 *
 * @param int $limit Max rows.
 * @return array
 */
function tf_get_all_logs( $limit = 50 ) {
	$tasks = get_posts(
		array(
			'post_type'   => 'tf_task',
			'post_status' => 'any',
			'numberposts' => -1,
			'fields'      => 'ids',
		)
	);

	$all = array();
	foreach ( $tasks as $task_id ) {
		$logs = tf_get_task_logs( $task_id );
		foreach ( $logs as $log ) {
			$all[] = $log;
		}
	}

	usort(
		$all,
		static function ( $a, $b ) {
			return (int) $b['end_time'] <=> (int) $a['end_time'];
		}
	);

	return array_slice( $all, 0, $limit );
}

/**
 * Duration formatter.
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
 * Datetime formatter.
 *
 * @param int $timestamp Unix timestamp.
 * @return string
 */
function tf_format_datetime( $timestamp ) {
	$timestamp = (int) $timestamp;
	if ( $timestamp <= 0 ) {
		return '—';
	}

	return wp_date( 'Y-m-d H:i:s', $timestamp );
}
