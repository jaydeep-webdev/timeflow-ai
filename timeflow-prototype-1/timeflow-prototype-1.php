<?php
/**
 * Plugin Name: Timeflow Prototype 1
 * Description: Minimal task and time tracking prototype for WordPress.
 * Version: 1.0.0
 * Author: Timeflow
 * Text Domain: timeflow-prototype-1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TF_PLUGIN_VERSION', '1.0.0' );
define( 'TF_PLUGIN_FILE', __FILE__ );
define( 'TF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once TF_PLUGIN_DIR . 'includes/post-types.php';
require_once TF_PLUGIN_DIR . 'includes/timer.php';
require_once TF_PLUGIN_DIR . 'includes/ajax.php';

/**
 * Flush rewrites on activation.
 */
function tf_activate_plugin() {
	tf_register_post_types();
	flush_rewrite_rules();
}
register_activation_hook( TF_PLUGIN_FILE, 'tf_activate_plugin' );

/**
 * Flush rewrites on deactivation.
 */
function tf_deactivate_plugin() {
	flush_rewrite_rules();
}
register_deactivation_hook( TF_PLUGIN_FILE, 'tf_deactivate_plugin' );

/**
 * Enqueue admin assets only for task edit screens.
 */
function tf_enqueue_admin_assets( $hook ) {
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $screen || 'tf_task' !== $screen->post_type ) {
		return;
	}

	wp_enqueue_style(
		'tf-admin-css',
		TF_PLUGIN_URL . 'assets/css/admin.css',
		array(),
		TF_PLUGIN_VERSION
	);

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
}
add_action( 'admin_enqueue_scripts', 'tf_enqueue_admin_assets' );
