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
			'methods'             => WP_REST_Server::READABLE,
			'permission_callback' => static function () {
				return current_user_can( 'edit_posts' );
			},
			'callback'            => 'tf_get_dashboard_data',
		)
	);

	register_rest_route(
		'timeflow/v1',
		'/tasks',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'permission_callback' => static function () {
				return current_user_can( 'edit_posts' );
			},
			'callback'            => 'tf_get_tasks_data',
		)
	);
}
add_action( 'rest_api_init', 'tf_register_dashboard_rest_route' );

/**
 * Collect tasks with optional project filtering.
 *
 * @param int|null $project_id Project ID filter.
 * @return array
 */
function tf_collect_tasks( $project_id = null ) {
	$args = array(
		'post_type'   => 'tf_task',
		'post_status' => 'any',
		'numberposts' => -1,
	);

	if ( null !== $project_id ) {
		$args['meta_query'] = array(
			array(
				'key'   => '_tf_project_id',
				'value' => absint( $project_id ),
			),
		);
	}

	$tasks = get_posts( $args );
	$rows  = array();

	foreach ( $tasks as $task ) {
		$task_project_id = (int) get_post_meta( $task->ID, '_tf_project_id', true );
		$rows[]          = array(
			'id'          => (int) $task->ID,
			'title'       => $task->post_title,
			'project_id'  => $task_project_id,
			'project'     => $task_project_id > 0 ? get_the_title( $task_project_id ) : __( 'Standalone', 'timeflow-ai-1-1' ),
			'total_time'  => (int) get_post_meta( $task->ID, '_tf_total_seconds', true ),
		);
	}

	return $rows;
}

/**
 * Build dashboard payload.
 *
 * @return array
 */
function tf_build_dashboard_data() {
	$projects = get_posts(
		array(
			'post_type'   => 'tf_project',
			'post_status' => 'any',
			'numberposts' => -1,
		)
	);
	$tasks    = tf_collect_tasks();
	$logs     = tf_get_all_logs( 20 );

	$project_rows = array();
	$total_time   = 0;
	foreach ( $projects as $project ) {
		$project_total = tf_get_project_total_time( $project->ID );
		$total_time   += $project_total;
		$project_rows[] = array(
			'id'         => (int) $project->ID,
			'title'      => $project->post_title,
			'task_count' => tf_get_project_task_count( $project->ID ),
			'total_time' => (int) $project_total,
		);
	}

	foreach ( $tasks as $task ) {
		if ( (int) $task['project_id'] <= 0 ) {
			$total_time += (int) $task['total_time'];
		}
	}

	$today_start = strtotime( wp_date( 'Y-m-d 00:00:00' ) );
	$today_end   = strtotime( wp_date( 'Y-m-d 23:59:59' ) );
	$today_time  = 0;
	foreach ( $logs as $log ) {
		$end = (int) $log['end_time'];
		if ( $end >= $today_start && $end <= $today_end ) {
			$today_time += (int) $log['duration'];
		}
	}

	$log_rows = array();
	foreach ( $logs as $log ) {
		$log_rows[] = array(
			'project'  => (int) $log['project_id'] > 0 ? get_the_title( (int) $log['project_id'] ) : __( 'Standalone', 'timeflow-ai-1-1' ),
			'task'     => get_the_title( (int) $log['task_id'] ),
			'duration' => (int) $log['duration'],
			'type'     => sanitize_text_field( $log['type'] ),
			'start'    => (int) $log['start_time'],
			'end'      => (int) $log['end_time'],
		);
	}

	$active_task_id    = (int) get_option( 'tf_active_task', 0 );
	$active_project_id = (int) get_option( 'tf_active_project', 0 );

	return array(
		'projects'       => $project_rows,
		'tasks'          => $tasks,
		'logs'           => $log_rows,
		'active_task'    => $active_task_id > 0 ? array( 'id' => $active_task_id, 'title' => get_the_title( $active_task_id ) ) : null,
		'active_project' => $active_project_id > 0 ? array( 'id' => $active_project_id, 'title' => get_the_title( $active_project_id ) ) : null,
		'total_projects' => count( $projects ),
		'total_tasks'    => count( $tasks ),
		'total_time'     => (int) $total_time,
		'today_time'     => (int) $today_time,
		'timer_start'    => (int) get_option( 'tf_timer_start', 0 ),
	);
}

/**
 * REST callback for dashboard.
 *
 * @return WP_REST_Response
 */
function tf_get_dashboard_data() {
	return rest_ensure_response( tf_build_dashboard_data() );
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
