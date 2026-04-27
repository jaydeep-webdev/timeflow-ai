<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register dashboard REST route.
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
}
add_action( 'rest_api_init', 'tf_register_dashboard_rest_route' );

/**
 * Build dashboard payload.
 *
 * @return array
 */
function tf_build_dashboard_data() {
	$projects = get_posts(
		array(
			'post_type'      => 'tf_project',
			'post_status'    => 'any',
			'numberposts'    => -1,
		)
	);
	$tasks    = get_posts(
		array(
			'post_type'      => 'tf_task',
			'post_status'    => 'any',
			'numberposts'    => -1,
		)
	);
	$logs     = tf_get_all_logs( 20 );

	$total_time = 0;
	foreach ( $projects as $project ) {
		$total_time += (int) get_post_meta( $project->ID, '_tf_total_seconds', true );
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

	$project_rows = array();
	foreach ( $projects as $project ) {
		$project_rows[] = array(
			'id'         => (int) $project->ID,
			'title'      => $project->post_title,
			'task_count' => tf_get_project_task_count( $project->ID ),
			'total_time' => (int) get_post_meta( $project->ID, '_tf_total_seconds', true ),
		);
	}

	$task_rows = array();
	foreach ( $tasks as $task ) {
		$task_rows[] = array(
			'id'          => (int) $task->ID,
			'title'       => $task->post_title,
			'project_id'  => (int) get_post_meta( $task->ID, '_tf_project_id', true ),
			'project'     => get_the_title( (int) get_post_meta( $task->ID, '_tf_project_id', true ) ),
			'status'      => get_post_meta( $task->ID, '_tf_status', true ),
			'total_time'  => (int) get_post_meta( $task->ID, '_tf_total_seconds', true ),
		);
	}

	$log_rows = array();
	foreach ( $logs as $log ) {
		$log_rows[] = array(
			'project'   => get_the_title( (int) $log['project_id'] ),
			'task'      => get_the_title( (int) $log['task_id'] ),
			'duration'  => (int) $log['duration'],
			'type'      => sanitize_text_field( $log['type'] ),
			'start'     => (int) $log['start_time'],
			'end'       => (int) $log['end_time'],
		);
	}

	$active_task_id    = (int) get_option( 'tf_active_task', 0 );
	$active_project_id = (int) get_option( 'tf_active_project', 0 );

	return array(
		'total_projects' => count( $projects ),
		'total_tasks'    => count( $tasks ),
		'total_time'     => (int) $total_time,
		'today_time'     => (int) $today_time,
		'active_task'    => $active_task_id > 0 ? array( 'id' => $active_task_id, 'title' => get_the_title( $active_task_id ) ) : null,
		'active_project' => $active_project_id > 0 ? array( 'id' => $active_project_id, 'title' => get_the_title( $active_project_id ) ) : null,
		'timer_start'    => (int) get_option( 'tf_timer_start', 0 ),
		'projects'       => $project_rows,
		'tasks'          => $task_rows,
		'logs'           => $log_rows,
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
