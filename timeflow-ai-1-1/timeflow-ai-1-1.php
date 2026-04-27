<?php
/**
 * Plugin Name: Timeflow AI 1.1
 * Description: Lightweight projects, tasks, timer tracking, and admin dashboard control panel.
 * Version: 1.1.0
 * Author: Timeflow
 * Text Domain: timeflow-ai-1-1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TF_PLUGIN_VERSION', '1.1.0' );
define( 'TF_PLUGIN_FILE', __FILE__ );
define( 'TF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once TF_PLUGIN_DIR . 'includes/post-types.php';
require_once TF_PLUGIN_DIR . 'includes/project.php';
require_once TF_PLUGIN_DIR . 'includes/task.php';
require_once TF_PLUGIN_DIR . 'includes/timer.php';
require_once TF_PLUGIN_DIR . 'includes/dashboard-data.php';
require_once TF_PLUGIN_DIR . 'includes/ajax.php';
require_once TF_PLUGIN_DIR . 'admin/dashboard.php';

/**
 * Activation hook.
 */
function tf_activate_plugin() {
	tf_register_post_types();
	flush_rewrite_rules();
}
register_activation_hook( TF_PLUGIN_FILE, 'tf_activate_plugin' );

/**
 * Deactivation hook.
 */
function tf_deactivate_plugin() {
	flush_rewrite_rules();
}
register_deactivation_hook( TF_PLUGIN_FILE, 'tf_deactivate_plugin' );

/**
 * Enqueue admin assets for task/project screens and dashboard.
 *
 * @param string $hook Hook name.
 */
function tf_enqueue_admin_assets( $hook ) {
	$screen = get_current_screen();
	if ( ! $screen ) {
		return;
	}

	$is_task_screen      = in_array( $hook, array( 'post.php', 'post-new.php' ), true ) && 'tf_task' === $screen->post_type;
	$is_dashboard_screen = 'toplevel_page_tf-dashboard' === $hook;

	if ( ! $is_task_screen && ! $is_dashboard_screen ) {
		return;
	}

	wp_enqueue_style( 'tf-admin-css', TF_PLUGIN_URL . 'assets/css/admin.css', array(), TF_PLUGIN_VERSION );

	wp_enqueue_script(
		'tf-timer-js',
		TF_PLUGIN_URL . 'assets/js/timer.js',
		array( 'jquery' ),
		TF_PLUGIN_VERSION,
		true
	);

	wp_localize_script(
		'tf-timer-js',
		'tfTimer',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'tf_timer_nonce' ),
		)
	);

	if ( $is_dashboard_screen ) {
		wp_enqueue_script(
			'tf-dashboard-js',
			TF_PLUGIN_URL . 'assets/js/dashboard.js',
			array( 'jquery' ),
			TF_PLUGIN_VERSION,
			true
		);

		wp_localize_script(
			'tf-dashboard-js',
			'tfDashboard',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'tf_timer_nonce' ),
				'restUrl' => esc_url_raw( rest_url( 'timeflow/v1/dashboard' ) ),
			)
		);
	}
}
add_action( 'admin_enqueue_scripts', 'tf_enqueue_admin_assets' );
