<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get project list for dropdowns.
 *
 * @return array<int,WP_Post>
 */
function tf_get_projects_for_select() {
	$projects = get_posts(
		array(
			'post_type'      => 'tf_project',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'numberposts'    => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'suppress_filters' => false,
		)
	);

	return is_array( $projects ) ? $projects : array();
}

/**
 * Add time to project total.
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

	$current = (int) get_post_meta( $project_id, '_tf_total_seconds', true );
	$new     = max( 0, $current + (int) $seconds );
	update_post_meta( $project_id, '_tf_total_seconds', $new );

	return $new;
}

/**
 * Get task count for project.
 *
 * @param int $project_id Project ID.
 * @return int
 */
function tf_get_project_task_count( $project_id ) {
	$query = new WP_Query(
		array(
			'post_type'      => 'tf_task',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'   => '_tf_project_id',
					'value' => absint( $project_id ),
				),
			),
		)
	);

	return (int) $query->found_posts;
}
