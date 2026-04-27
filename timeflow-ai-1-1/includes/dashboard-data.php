<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register REST routes.
 */
function tf_register_dashboard_rest_route() {
	$permission = static function () {
		return current_user_can( 'edit_posts' );
	};

	register_rest_route(
		'timeflow/v1',
		'/dashboard',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'permission_callback' => $permission,
			'callback'            => 'tf_get_dashboard_data',
		)
	);

	register_rest_route(
		'timeflow/v1',
		'/tasks',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'permission_callback' => $permission,
			'callback'            => 'tf_get_tasks_data',
		)
	);

	register_rest_route(
		'timeflow/v1',
		'/action',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'permission_callback' => $permission,
			'callback'            => 'tf_handle_dashboard_action',
		)
	);
}
add_action( 'rest_api_init', 'tf_register_dashboard_rest_route' );

/**
 * Get task project id, supporting legacy meta.
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
 * Collect tasks with optional project filtering.
 *
 * @param int|null $project_id Project ID filter.
 * @return array
 */
function tf_collect_tasks( $project_id = null ) {
	$tasks = null === $project_id ? get_posts(
		array(
			'post_type'   => 'tf_task',
			'post_status' => 'any',
			'numberposts' => -1,
		)
	) : tf_get_tasks_by_project( $project_id );

	$rows = array();
	foreach ( $tasks as $task ) {
		$task_project_id = tf_get_task_project_id( $task->ID );
		$rows[]          = array(
			'id'         => (int) $task->ID,
			'title'      => $task->post_title,
			'project_id' => $task_project_id,
			'project'    => $task_project_id > 0 ? get_the_title( $task_project_id ) : __( 'Standalone', 'timeflow-ai-1-1' ),
			'total_time' => (int) get_post_meta( $task->ID, '_tf_total_seconds', true ),
		);
	}

	return $rows;
}

/**
 * Build dashboard payload.
 *
 * @return array
 */
function tf_build_dashboard_payload() {
	$projects = get_posts(
		array(
			'post_type'   => 'tf_project',
			'post_status' => 'any',
			'numberposts' => -1,
		)
	);
	$tasks    = tf_collect_tasks();
	$logs     = tf_get_all_logs( 20 );

	$project_data = array();
	foreach ( $projects as $project ) {
		$project_tasks = tf_get_tasks_by_project( $project->ID );
		$project_time  = 0;
		foreach ( $project_tasks as $task ) {
			$project_time += (int) get_post_meta( $task->ID, '_tf_total_seconds', true );
		}

		$project_data[] = array(
			'id'         => (int) $project->ID,
			'title'      => $project->post_title,
			'task_count' => count( $project_tasks ),
			'total_time' => $project_time,
		);
	}

	$total_time = 0;
	$today_time = 0;
	$today      = wp_date( 'Y-m-d' );
	$log_rows   = array();

	foreach ( $logs as $log ) {
		$duration   = (int) $log['duration'];
		$total_time += $duration;

		$start = (int) $log['start_time'];
		if ( $start > 0 && wp_date( 'Y-m-d', $start ) === $today ) {
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

	$active_task_id    = (int) get_option( 'tf_active_task', 0 );
	$active_project_id = (int) get_option( 'tf_active_project', 0 );

	$data = array(
		'projects'       => $project_data,
		'tasks'          => $tasks,
		'logs'           => $log_rows,
		'active_task'    => $active_task_id > 0 ? array( 'id' => $active_task_id, 'title' => get_the_title( $active_task_id ) ) : null,
		'active_project' => $active_project_id > 0 ? array( 'id' => $active_project_id, 'title' => get_the_title( $active_project_id ) ) : null,
		'total_projects' => count( $projects ),
		'total_tasks'    => count( $tasks ),
		'total_time'     => $total_time,
		'today_time'     => $today_time,
		'timer_start'    => (int) get_option( 'tf_timer_start', 0 ),
	);

	error_log( print_r( $data, true ) );

	return $data;
}

/**
 * REST callback for dashboard.
 *
 * @return WP_REST_Response
 */
function tf_get_dashboard_data() {
	return rest_ensure_response( tf_build_dashboard_payload() );
}

/**
 * REST callback for filtered tasks.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function tf_get_tasks_data( $request ) {
	$project_id = $request->get_param( 'project_id' );
	if ( null === $project_id || '' === $project_id ) {
		return rest_ensure_response( array() );
	}

	return rest_ensure_response( tf_collect_tasks( absint( $project_id ) ) );
}

/**
 * REST callback for dashboard timer actions.
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
