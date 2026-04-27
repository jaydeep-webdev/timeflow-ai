<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add meta boxes for tasks.
 */
function tf_add_task_meta_boxes() {
	add_meta_box(
		'tf_task_details',
		__( 'Task Controls', 'timeflow-ai-1-1' ),
		'tf_render_task_meta_box',
		'tf_task',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'tf_add_task_meta_boxes' );

/**
 * Save task metadata.
 *
 * @param int $post_id Post ID.
 */
function tf_save_task_meta( $post_id ) {
	if ( ! isset( $_POST['tf_task_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tf_task_meta_nonce'] ) ), 'tf_save_task_meta' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$status = isset( $_POST['tf_task_status'] ) ? sanitize_text_field( wp_unslash( $_POST['tf_task_status'] ) ) : 'pending';
	if ( in_array( $status, array( 'pending', 'in_progress', 'completed' ), true ) ) {
		update_post_meta( $post_id, '_tf_status', $status );
	}

	$project_id = isset( $_POST['tf_project_id'] ) ? absint( $_POST['tf_project_id'] ) : 0;
	if ( $project_id > 0 && 'tf_project' === get_post_type( $project_id ) ) {
		update_post_meta( $post_id, 'project_id', $project_id );
		update_post_meta( $post_id, '_tf_project_id', $project_id );
	} else {
		update_post_meta( $post_id, 'project_id', 0 );
		update_post_meta( $post_id, '_tf_project_id', 0 );
	}
}
add_action( 'save_post_tf_task', 'tf_save_task_meta' );

/**
 * Render task details UI.
 *
 * @param WP_Post $post Post object.
 */
function tf_render_task_meta_box( $post ) {
	wp_nonce_field( 'tf_save_task_meta', 'tf_task_meta_nonce' );

	$status      = get_post_meta( $post->ID, '_tf_status', true );
	$project_id  = tf_get_task_project_id( $post->ID );
	$total_time  = (int) get_post_meta( $post->ID, 'total_time', true );
	if ( $total_time <= 0 ) {
		$total_time = (int) get_post_meta( $post->ID, '_tf_total_seconds', true );
	}
	$logs        = tf_get_task_logs( $post->ID );
	$projects    = tf_get_projects_for_select();
	$active_task = (int) get_option( 'tf_active_task', 0 );
	$is_active   = $active_task === (int) $post->ID;

	if ( 0 === $project_id && isset( $_GET['tf_project_id'] ) ) {
		$prefill_id = absint( $_GET['tf_project_id'] );
		if ( $prefill_id > 0 && 'tf_project' === get_post_type( $prefill_id ) ) {
			$project_id = $prefill_id;
		}
	}

	if ( empty( $status ) ) {
		$status = 'pending';
	}
	?>
	<div class="tf-field-row">
		<label for="tf_project_id"><strong><?php esc_html_e( 'Project (required when creating under a project)', 'timeflow-ai-1-1' ); ?></strong></label>
		<select id="tf_project_id" name="tf_project_id">
			<option value="0"><?php esc_html_e( 'Standalone task (no project)', 'timeflow-ai-1-1' ); ?></option>
			<?php foreach ( $projects as $project ) : ?>
				<option value="<?php echo esc_attr( $project->ID ); ?>" <?php selected( $project_id, (int) $project->ID ); ?>>
					<?php echo esc_html( $project->post_title ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description tf-project-warning" <?php echo $project_id > 0 ? 'style="display:none;"' : ''; ?>>
			<?php esc_html_e( 'Warning: no project selected. This will be saved as a standalone task.', 'timeflow-ai-1-1' ); ?>
		</p>
	</div>

	<div class="tf-field-row">
		<label for="tf_task_status"><strong><?php esc_html_e( 'Status', 'timeflow-ai-1-1' ); ?></strong></label>
		<select id="tf_task_status" name="tf_task_status">
			<option value="pending" <?php selected( $status, 'pending' ); ?>><?php esc_html_e( 'Pending', 'timeflow-ai-1-1' ); ?></option>
			<option value="in_progress" <?php selected( $status, 'in_progress' ); ?>><?php esc_html_e( 'In Progress', 'timeflow-ai-1-1' ); ?></option>
			<option value="completed" <?php selected( $status, 'completed' ); ?>><?php esc_html_e( 'Completed', 'timeflow-ai-1-1' ); ?></option>
		</select>
	</div>

	<div class="tf-field-row">
		<p><strong><?php esc_html_e( 'Total Time Spent', 'timeflow-ai-1-1' ); ?>:</strong> <span id="tf-total-time-display"><?php echo esc_html( tf_format_duration( $total_time ) ); ?></span></p>
	</div>

	<div class="tf-field-row tf-controls" data-task-id="<?php echo esc_attr( $post->ID ); ?>" data-project-id="<?php echo esc_attr( $project_id ); ?>">
		<button type="button" class="button button-primary" id="tf-start-timer" <?php disabled( $is_active ); ?>><?php esc_html_e( 'Start Timer', 'timeflow-ai-1-1' ); ?></button>
		<button type="button" class="button" id="tf-stop-timer" <?php disabled( ! $is_active ); ?>><?php esc_html_e( 'Stop Timer', 'timeflow-ai-1-1' ); ?></button>
	</div>

	<div class="tf-field-row tf-controls" data-task-id="<?php echo esc_attr( $post->ID ); ?>">
		<label for="tf-manual-minutes"><strong><?php esc_html_e( 'Add Time (minutes)', 'timeflow-ai-1-1' ); ?></strong></label>
		<input type="number" id="tf-manual-minutes" min="1" step="1" />
		<button type="button" class="button" id="tf-add-time"><?php esc_html_e( 'Add Time', 'timeflow-ai-1-1' ); ?></button>
	</div>

	<div id="tf-message" class="tf-message" aria-live="polite"></div>

	<h4><?php esc_html_e( 'Time Logs', 'timeflow-ai-1-1' ); ?></h4>
	<table class="widefat striped tf-log-table">
		<thead>
		<tr>
			<th><?php esc_html_e( 'Type', 'timeflow-ai-1-1' ); ?></th>
			<th><?php esc_html_e( 'Project', 'timeflow-ai-1-1' ); ?></th>
			<th><?php esc_html_e( 'Start', 'timeflow-ai-1-1' ); ?></th>
			<th><?php esc_html_e( 'End', 'timeflow-ai-1-1' ); ?></th>
			<th><?php esc_html_e( 'Duration', 'timeflow-ai-1-1' ); ?></th>
		</tr>
		</thead>
		<tbody id="tf-log-body">
		<?php if ( empty( $logs ) ) : ?>
			<tr><td colspan="5"><?php esc_html_e( 'No logs yet.', 'timeflow-ai-1-1' ); ?></td></tr>
		<?php else : ?>
			<?php foreach ( array_reverse( $logs ) as $log ) : ?>
				<tr>
					<td><?php echo esc_html( ucfirst( $log['type'] ) ); ?></td>
					<td><?php echo esc_html( (int) $log['project_id'] > 0 ? get_the_title( (int) $log['project_id'] ) : __( 'Standalone', 'timeflow-ai-1-1' ) ); ?></td>
					<td><?php echo esc_html( tf_format_datetime( (int) $log['start_time'] ) ); ?></td>
					<td><?php echo esc_html( tf_format_datetime( (int) $log['end_time'] ) ); ?></td>
					<td><?php echo esc_html( tf_format_duration( (int) $log['duration'] ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>
	<?php
}
