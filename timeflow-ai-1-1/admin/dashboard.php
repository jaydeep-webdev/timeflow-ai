<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register admin menu.
 */
function tf_register_admin_menu() {
	add_menu_page(
		__( 'Timeflow Dashboard', 'timeflow-ai-1-1' ),
		__( 'Timeflow', 'timeflow-ai-1-1' ),
		'edit_posts',
		'tf-dashboard',
		'tf_render_dashboard_page',
		'dashicons-clock',
		26
	);

	add_submenu_page(
		'tf-dashboard',
		__( 'Dashboard', 'timeflow-ai-1-1' ),
		__( 'Dashboard', 'timeflow-ai-1-1' ),
		'edit_posts',
		'tf-dashboard',
		'tf_render_dashboard_page'
	);

	add_submenu_page(
		'tf-dashboard',
		__( 'Projects', 'timeflow-ai-1-1' ),
		__( 'Projects', 'timeflow-ai-1-1' ),
		'edit_posts',
		'edit.php?post_type=tf_project'
	);

	add_submenu_page(
		'tf-dashboard',
		__( 'Tasks', 'timeflow-ai-1-1' ),
		__( 'Tasks', 'timeflow-ai-1-1' ),
		'edit_posts',
		'edit.php?post_type=tf_task'
	);
}
add_action( 'admin_menu', 'tf_register_admin_menu' );

/**
 * Render dashboard markup.
 */
function tf_render_dashboard_page() {
	?>
	<div class="wrap tf-dashboard-wrap" id="tf-dashboard">
		<div class="tf-dashboard-header">
			<h1><?php esc_html_e( 'Timeflow AI 1.2 Dashboard', 'timeflow-ai-1-1' ); ?></h1>
			<p><?php esc_html_e( 'Track tasks, projects, and active timer from one control panel.', 'timeflow-ai-1-1' ); ?></p>
		</div>

		<div class="tf-dashboard-grid tf-top-grid">
			<section class="tf-card tf-card-highlight">
				<h2><?php esc_html_e( 'Active Timer', 'timeflow-ai-1-1' ); ?></h2>
				<p><strong><?php esc_html_e( 'Task:', 'timeflow-ai-1-1' ); ?></strong> <span id="tf-active-task">—</span></p>
				<p><strong><?php esc_html_e( 'Project:', 'timeflow-ai-1-1' ); ?></strong> <span id="tf-active-project">—</span></p>
				<p><strong><?php esc_html_e( 'Started:', 'timeflow-ai-1-1' ); ?></strong> <span id="tf-active-start">—</span></p>
				<div class="tf-controls tf-btn-row">
					<button class="button button-primary" id="tf-dashboard-start"><?php esc_html_e( 'Start Timer', 'timeflow-ai-1-1' ); ?></button>
					<button class="button" id="tf-dashboard-stop"><?php esc_html_e( 'Stop Timer', 'timeflow-ai-1-1' ); ?></button>
				</div>
				<div class="tf-controls tf-btn-row">
					<input type="number" min="1" step="1" id="tf-dashboard-manual-minutes" placeholder="<?php esc_attr_e( 'Minutes', 'timeflow-ai-1-1' ); ?>" />
					<button class="button" id="tf-dashboard-add-time"><?php esc_html_e( 'Add Time', 'timeflow-ai-1-1' ); ?></button>
				</div>
			</section>

			<section class="tf-card">
				<h2><?php esc_html_e( 'Quick Start', 'timeflow-ai-1-1' ); ?></h2>
				<p>
					<label for="tf-quick-project"><?php esc_html_e( 'Project', 'timeflow-ai-1-1' ); ?></label><br />
					<select id="tf-quick-project"></select>
				</p>
				<p>
					<label for="tf-quick-task"><?php esc_html_e( 'Task', 'timeflow-ai-1-1' ); ?></label><br />
					<select id="tf-quick-task"></select>
				</p>
			</section>

			<section class="tf-card">
				<h2><?php esc_html_e( 'Stats', 'timeflow-ai-1-1' ); ?></h2>
				<div class="tf-stat-list">
					<div class="tf-stat-item"><span><?php esc_html_e( 'Total Projects', 'timeflow-ai-1-1' ); ?></span><strong id="tf-stat-projects">0</strong></div>
					<div class="tf-stat-item"><span><?php esc_html_e( 'Total Tasks', 'timeflow-ai-1-1' ); ?></span><strong id="tf-stat-tasks">0</strong></div>
					<div class="tf-stat-item"><span><?php esc_html_e( 'Total Time', 'timeflow-ai-1-1' ); ?></span><strong id="tf-stat-total-time">0s</strong></div>
					<div class="tf-stat-item"><span><?php esc_html_e( 'Today\'s Time', 'timeflow-ai-1-1' ); ?></span><strong id="tf-stat-today-time">0s</strong></div>
				</div>
			</section>
		</div>

		<div id="tf-dashboard-message" class="tf-message" aria-live="polite"></div>

		<div class="tf-dashboard-grid tf-dashboard-lists">
			<section class="tf-card">
				<h2><?php esc_html_e( 'Projects Overview', 'timeflow-ai-1-1' ); ?></h2>
				<table class="widefat striped">
					<thead><tr><th><?php esc_html_e( 'Project', 'timeflow-ai-1-1' ); ?></th><th><?php esc_html_e( 'Tasks', 'timeflow-ai-1-1' ); ?></th><th><?php esc_html_e( 'Total Time', 'timeflow-ai-1-1' ); ?></th></tr></thead>
					<tbody id="tf-projects-body"><tr><td colspan="3">—</td></tr></tbody>
				</table>
			</section>

			<section class="tf-card">
				<h2><?php esc_html_e( 'Tasks Quick Access', 'timeflow-ai-1-1' ); ?></h2>
				<table class="widefat striped">
					<thead><tr><th><?php esc_html_e( 'Task', 'timeflow-ai-1-1' ); ?></th><th><?php esc_html_e( 'Project', 'timeflow-ai-1-1' ); ?></th><th><?php esc_html_e( 'Time', 'timeflow-ai-1-1' ); ?></th><th><?php esc_html_e( 'Action', 'timeflow-ai-1-1' ); ?></th></tr></thead>
					<tbody id="tf-tasks-body"><tr><td colspan="4">—</td></tr></tbody>
				</table>
			</section>

			<section class="tf-card tf-card-full">
				<h2><?php esc_html_e( 'Recent Time Logs', 'timeflow-ai-1-1' ); ?></h2>
				<table class="widefat striped">
					<thead><tr><th><?php esc_html_e( 'Project', 'timeflow-ai-1-1' ); ?></th><th><?php esc_html_e( 'Task', 'timeflow-ai-1-1' ); ?></th><th><?php esc_html_e( 'Duration', 'timeflow-ai-1-1' ); ?></th><th><?php esc_html_e( 'Type', 'timeflow-ai-1-1' ); ?></th></tr></thead>
					<tbody id="tf-logs-body"><tr><td colspan="4">—</td></tr></tbody>
				</table>
			</section>
		</div>
	</div>
	<?php
}
