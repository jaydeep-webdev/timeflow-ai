<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register required post type.
 */
function tf_register_post_types() {
	$labels = array(
		'name'          => __( 'Tasks', 'timeflow-prototype-1' ),
		'singular_name' => __( 'Task', 'timeflow-prototype-1' ),
		'add_new_item'  => __( 'Add New Task', 'timeflow-prototype-1' ),
		'edit_item'     => __( 'Edit Task', 'timeflow-prototype-1' ),
	);

	register_post_type(
		'tf_task',
		array(
			'labels'       => $labels,
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => true,
			'menu_icon'    => 'dashicons-clipboard',
			'supports'     => array( 'title', 'editor' ),
			'has_archive'  => false,
		)
	);
}
add_action( 'init', 'tf_register_post_types' );

/**
 * Add task management meta box.
 */
function tf_add_task_meta_boxes() {
	add_meta_box(
		'tf_task_timer_box',
		__( 'Time Tracking', 'timeflow-prototype-1' ),
		'tf_render_task_timer_meta_box',
		'tf_task',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'tf_add_task_meta_boxes' );

/**
 * Save status field for task.
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

	if ( isset( $_POST['tf_task_status'] ) ) {
		$status = sanitize_text_field( wp_unslash( $_POST['tf_task_status'] ) );
		$valid  = array( 'pending', 'in_progress', 'completed' );

		if ( in_array( $status, $valid, true ) ) {
			update_post_meta( $post_id, '_tf_status', $status );
		}
	}
}
add_action( 'save_post_tf_task', 'tf_save_task_meta' );

/**
 * Render task timer meta box.
 *
 * @param WP_Post $post Post object.
 */
function tf_render_task_timer_meta_box( $post ) {
	wp_nonce_field( 'tf_save_task_meta', 'tf_task_meta_nonce' );

	$status      = get_post_meta( $post->ID, '_tf_status', true );
	$total_time  = (int) get_post_meta( $post->ID, '_tf_total_seconds', true );
	$logs        = tf_get_task_logs( $post->ID );
	$active_task = (int) get_option( 'tf_active_task', 0 );
	$is_active   = $active_task === (int) $post->ID;

	if ( empty( $status ) ) {
		$status = 'pending';
	}
	?>
	<div class="tf-field-row">
		<label for="tf_task_status"><strong><?php esc_html_e( 'Status', 'timeflow-prototype-1' ); ?></strong></label>
		<select id="tf_task_status" name="tf_task_status">
			<option value="pending" <?php selected( $status, 'pending' ); ?>><?php esc_html_e( 'Pending', 'timeflow-prototype-1' ); ?></option>
			<option value="in_progress" <?php selected( $status, 'in_progress' ); ?>><?php esc_html_e( 'In Progress', 'timeflow-prototype-1' ); ?></option>
			<option value="completed" <?php selected( $status, 'completed' ); ?>><?php esc_html_e( 'Completed', 'timeflow-prototype-1' ); ?></option>
		</select>
	</div>

	<div class="tf-field-row">
		<p><strong><?php esc_html_e( 'Total Time Spent', 'timeflow-prototype-1' ); ?>:</strong> <span id="tf-total-time-display"><?php echo esc_html( tf_format_duration( $total_time ) ); ?></span></p>
	</div>

	<div class="tf-field-row tf-controls" data-task-id="<?php echo esc_attr( $post->ID ); ?>">
		<button type="button" class="button button-primary" id="tf-start-timer" <?php disabled( $is_active ); ?>>
			<?php esc_html_e( 'Start Timer', 'timeflow-prototype-1' ); ?>
		</button>
		<button type="button" class="button" id="tf-stop-timer" <?php disabled( ! $is_active ); ?>>
			<?php esc_html_e( 'Stop Timer', 'timeflow-prototype-1' ); ?>
		</button>
	</div>

	<div class="tf-field-row tf-controls" data-task-id="<?php echo esc_attr( $post->ID ); ?>">
		<label for="tf-manual-minutes"><strong><?php esc_html_e( 'Add Time (minutes)', 'timeflow-prototype-1' ); ?></strong></label>
		<input type="number" id="tf-manual-minutes" min="1" step="1" />
		<button type="button" class="button" id="tf-add-time"><?php esc_html_e( 'Add Time', 'timeflow-prototype-1' ); ?></button>
	</div>

	<div id="tf-message" class="tf-message" aria-live="polite"></div>

	<h4><?php esc_html_e( 'Time Logs', 'timeflow-prototype-1' ); ?></h4>
	<table class="widefat striped tf-log-table">
		<thead>
		<tr>
			<th><?php esc_html_e( 'Type', 'timeflow-prototype-1' ); ?></th>
			<th><?php esc_html_e( 'Start', 'timeflow-prototype-1' ); ?></th>
			<th><?php esc_html_e( 'End', 'timeflow-prototype-1' ); ?></th>
			<th><?php esc_html_e( 'Duration', 'timeflow-prototype-1' ); ?></th>
		</tr>
		</thead>
		<tbody id="tf-log-body">
		<?php if ( empty( $logs ) ) : ?>
			<tr><td colspan="4"><?php esc_html_e( 'No logs yet.', 'timeflow-prototype-1' ); ?></td></tr>
		<?php else : ?>
			<?php foreach ( array_reverse( $logs ) as $log ) : ?>
				<tr>
					<td><?php echo esc_html( ucfirst( $log['type'] ) ); ?></td>
					<td><?php echo esc_html( tf_format_datetime( $log['start_time'] ) ); ?></td>
					<td><?php echo esc_html( tf_format_datetime( $log['end_time'] ) ); ?></td>
					<td><?php echo esc_html( tf_format_duration( (int) $log['duration'] ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>
	<?php
}
