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
			'post_type'        => 'tf_project',
			'post_status'      => array( 'publish', 'draft', 'pending', 'private' ),
			'numberposts'      => -1,
			'orderby'          => 'title',
			'order'            => 'ASC',
			'suppress_filters' => false,
		)
	);

	return is_array( $projects ) ? $projects : array();
}

/**
 * Count tasks for project.
 *
 * @param int $project_id Project ID.
 * @return int
 */
function tf_get_project_task_count( $project_id ) {
	$query = new WP_Query(
		array(
			'post_type'      => 'tf_task',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'   => 'project_id',
					'value' => absint( $project_id ),
				),
			),
		)
	);

	return (int) $query->found_posts;
}

/**
 * Get project total time from all linked tasks.
 *
 * @param int $project_id Project ID.
 * @return int
 */
function tf_get_project_total_time( $project_id ) {
	$task_ids = get_posts(
		array(
			'post_type'      => 'tf_task',
			'post_status'    => 'any',
			'numberposts'    => -1,
			'fields'         => 'ids',
			'meta_key'       => 'project_id',
			'meta_value'     => absint( $project_id ),
		)
	);

	$total = 0;
	foreach ( $task_ids as $task_id ) {
		$task_total = (int) get_post_meta( $task_id, 'total_time', true );
		if ( $task_total <= 0 ) {
			$task_total = (int) get_post_meta( $task_id, '_tf_total_seconds', true );
		}
		$total += $task_total;
	}

	update_post_meta( absint( $project_id ), 'total_time', $total );
	update_post_meta( absint( $project_id ), '_tf_total_seconds', $total );

	return (int) $total;
}

/**
 * Add project side meta box.
 */
function tf_add_project_meta_boxes() {
	add_meta_box(
		'tf_project_actions',
		__( 'Project Actions', 'timeflow-ai-1-2' ),
		'tf_render_project_actions_box',
		'tf_project',
		'side',
		'high'
	);
}
add_action( 'add_meta_boxes', 'tf_add_project_meta_boxes' );

/**
 * Render project action box.
 *
 * @param WP_Post $post Post object.
 */
function tf_render_project_actions_box( $post ) {
	$url = add_query_arg(
		array(
			'post_type'     => 'tf_task',
			'tf_project_id' => absint( $post->ID ),
		),
		admin_url( 'post-new.php' )
	);
	?>
	<p>
		<a class="button button-primary" href="<?php echo esc_url( $url ); ?>">
			<?php esc_html_e( 'Add Task to Project', 'timeflow-ai-1-2' ); ?>
		</a>
	</p>
	<p><?php esc_html_e( 'This opens a new task with this project pre-selected.', 'timeflow-ai-1-2' ); ?></p>
	<?php
}
