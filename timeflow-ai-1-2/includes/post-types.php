<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register project and task post types.
 */
function tf_register_post_types() {
	register_post_type(
		'tf_project',
		array(
			'labels'       => array(
				'name'          => __( 'Projects', 'timeflow-ai-1-2' ),
				'singular_name' => __( 'Project', 'timeflow-ai-1-2' ),
			),
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => false,
			'menu_icon'    => 'dashicons-portfolio',
			'supports'     => array( 'title', 'editor' ),
		)
	);

	register_post_type(
		'tf_task',
		array(
			'labels'       => array(
				'name'          => __( 'Tasks', 'timeflow-ai-1-2' ),
				'singular_name' => __( 'Task', 'timeflow-ai-1-2' ),
			),
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => false,
			'menu_icon'    => 'dashicons-clipboard',
			'supports'     => array( 'title', 'editor' ),
		)
	);
}
add_action( 'init', 'tf_register_post_types' );
