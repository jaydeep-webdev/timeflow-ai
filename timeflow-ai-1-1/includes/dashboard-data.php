<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register REST routes.
 */
function tf_register_dashboard_rest_route() {
	register_rest_route(
		'timeflow/v1',
		'/dashboard',
		array(
			'methods'             => 'GET',
			'callback'            => 'tf_dashboard_data',
			'permission_callback' => '__return_true',
		)
	);

	register_rest_route(
		'timeflow/v1',
		'/tasks',
		array(
			'methods'             => 'GET',
			'callback'            => 'tf_get_tasks_data',
			'permission_callback' => '__return_true',
		)
	);

	register_rest_route(
		'timeflow/v1',
		'/action',
		array(
			'methods'             => 'POST',
			'callback'            => 'tf_handle_dashboard_action',
			'permission_callback' => '__return_true',
		)
	);
}
add_action( 'rest_api_init', 'tf_register_dashboard_rest_route' );

/**
 * Get task project id with legacy fallback.
 *
 * @param int $task_id Task ID.
 * @return int
 */
function tf_get_task_project_id( $task_id ) {
	$project_id = (int) get_post_meta( $task_id, 'project_id', true );
	if ( $project_id <= 0 ) {
		$project_id = (int) get_post_meta( $task_id, '_tf_project_id', true );
	}
	return max( 0, $project_id );
}

/**
 * Return tasks linked to a specific project.
 *
 * @param int $project_id Project ID.
 * @return array<int,WP_Post>
 */
function tf_get_tasks_by_project( $project_id ) {
	return get_posts(
		array(
			'post_type'   => 'tf_task',
			'post_status' => 'any',
			'numberposts' => -1,
			'meta_query'  => array(
				array(
					'key'     => 'project_id',
					'value'   => absint( $project_id ),
					'compare' => '=',
				),
			),
		)
	);
}

/**
 * Build task array for API.
 *
 * @param array $tasks Task posts.
 * @return array
 */
function tf_map_tasks_for_dashboard( $tasks ) {
	return array_map(
		static function ( $task ) {
			$total_time = (int) get_post_meta( $task->ID, 'total_time', true );
			if ( $total_time <= 0 ) {
				$total_time = (int) get_post_meta( $task->ID, '_tf_total_seconds', true );
			}

			$project_id = tf_get_task_project_id( $task->ID );

			return array(
				'id'         => (int) $task->ID,
				'title'      => $task->post_title,
				'project_id' => $project_id,
				'project'    => $project_id > 0 ? get_the_title( $project_id ) : __( 'Standalone', 'timeflow-ai-1-1' ),
				'total_time' => $total_time,
			);
		},
		$tasks
	);
}

/**
 * Dashboard API response.
 *
 * @return WP_REST_Response
 */
function tf_dashboard_data() {
	$projects = get_posts(
		array(
			'post_type'   => 'tf_project',
			'post_status' => 'any',
			'numberposts' => -1,
		)
	);
	$tasks    = get_posts(
		array(
			'post_type'   => 'tf_task',
			'post_status' => 'any',
			'numberposts' => -1,
		)
	);

	$task_data = tf_map_tasks_for_dashboard( $tasks );
	$logs      = tf_get_all_logs( 20 );

	$total_time = 0;
	foreach ( $task_data as $task ) {
		$total_time += (int) $task['total_time'];
	}

	$today_time = 0;
	$today      = wp_date( 'Y-m-d' );
	$log_rows   = array();
	foreach ( $logs as $log ) {
		$duration   = (int) $log['duration'];
		$start_time = isset( $log['start_time'] ) ? (int) $log['start_time'] : 0;
		if ( $start_time > 0 && wp_date( 'Y-m-d', $start_time ) === $today ) {
			$today_time += $duration;
		}
		$project_id = isset( $log['project_id'] ) ? (int) $log['project_id'] : 0;
		$task_id    = isset( $log['task_id'] ) ? (int) $log['task_id'] : 0;
		$log_rows[] = array(
			'project'  => $project_id > 0 ? get_the_title( $project_id ) : __( 'Standalone', 'timeflow-ai-1-1' ),
			'task'     => $task_id > 0 ? get_the_title( $task_id ) : __( 'Unknown', 'timeflow-ai-1-1' ),
			'duration' => $duration,
			'type'     => isset( $log['type'] ) ? sanitize_text_field( $log['type'] ) : 'manual',
		);
	}

	$project_data = array();
	foreach ( $projects as $project ) {
		$project_tasks = tf_get_tasks_by_project( $project->ID );
		$project_time  = 0;
		foreach ( $project_tasks as $task ) {
			$task_time = (int) get_post_meta( $task->ID, 'total_time', true );
			if ( $task_time <= 0 ) {
				$task_time = (int) get_post_meta( $task->ID, '_tf_total_seconds', true );
			}
			$project_time += $task_time;
		}

		$project_data[] = array(
			'id'         => (int) $project->ID,
			'title'      => $project->post_title,
			'task_count' => count( $project_tasks ),
			'total_time' => $project_time,
		);
	}

	$data = array(
		'projects'       => $project_data,
		'tasks'          => $task_data,
		'logs'           => $log_rows,
		'total_projects' => count( $projects ),
		'total_tasks'    => count( $tasks ),
		'total_time'     => $total_time,
		'today_time'     => $today_time,
		'active_task'    => (int) get_option( 'tf_active_task', 0 ) > 0 ? array( 'id' => (int) get_option( 'tf_active_task', 0 ), 'title' => get_the_title( (int) get_option( 'tf_active_task', 0 ) ) ) : null,
		'active_project' => (int) get_option( 'tf_active_project', 0 ) > 0 ? array( 'id' => (int) get_option( 'tf_active_project', 0 ), 'title' => get_the_title( (int) get_option( 'tf_active_project', 0 ) ) ) : null,
		'timer_start'    => (int) get_option( 'tf_timer_start', 0 ),
	);

	return rest_ensure_response( $data );
}

/**
 * Tasks endpoint.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function tf_get_tasks_data( $request ) {
	$project_id = absint( $request->get_param( 'project_id' ) );
	if ( $project_id <= 0 ) {
		return rest_ensure_response( array() );
	}
	return rest_ensure_response( tf_map_tasks_for_dashboard( tf_get_tasks_by_project( $project_id ) ) );
}

/**
 * Handle action endpoint.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function tf_handle_dashboard_action( $request ) {
	$action = sanitize_text_field( (string) $request->get_param( 'action' ) );

	if ( 'START_TIMER' === $action ) {
		$task_id = absint( $request->get_param( 'task_id' ) );
		$result  = tf_start_timer( $task_id );
		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => $result->get_error_message() ), 400 );
		}
		return rest_ensure_response( array( 'success' => true, 'data' => $result ) );
	}

	if ( 'STOP_TIMER' === $action ) {
		$result = tf_stop_timer();
		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => $result->get_error_message() ), 400 );
		}
		return rest_ensure_response( array( 'success' => true, 'data' => $result ) );
	}

	if ( 'ADD_TIME' === $action ) {
		$task_id = absint( $request->get_param( 'task_id' ) );
		$minutes = absint( $request->get_param( 'minutes' ) );
		$result  = tf_add_manual_time( $task_id, $minutes );
		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => $result->get_error_message() ), 400 );
		}
		return rest_ensure_response( array( 'success' => true, 'data' => $result ) );
	}

	return new WP_REST_Response( array( 'success' => false, 'message' => __( 'Invalid action.', 'timeflow-ai-1-1' ) ), 400 );
}
