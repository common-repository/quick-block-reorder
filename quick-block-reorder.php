<?php
/**
 * Plugin Name:     Quick Block Reorder
 * Description:     Reorder the blocks without opening block editor
 * Author:          rebelwageslave
 * Author URI:      https://github.com/rebelwageslave
 * Text Domain:     quick-block-reorder
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         Quick_Block_Reorder
 */


require_once __DIR__ . '/rest-api.php';
// Your code starts here.


add_filter( 'manage_posts_columns', function ( $columns, $post_type ) {
	$columns['quick_block_reorder'] = __( 'Quick Reoorder', 'quick-block-reorder' );

	return $columns;
}, 10, 2 );

add_action( 'manage_posts_custom_column', function ( $column_name, $post_id ) {
	echo "<a class='qbr-click-handler' id='qbr-${post_id}' href='#'><span class=\"dashicons dashicons-image-rotate-left\"></span></a>";
}, 10, 2 );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( $hook !== 'edit.php' ) {
		return;
	}
	wp_enqueue_script( 'quick-block-reorder-ui', plugin_dir_url( __FILE__ ) . '/js/dist/block-reorder.js', array(
		'react',
		'wp-polyfill',
		'react-dom',
		'wp-i18n'
	) );

	wp_localize_script('quick-block-reorder-ui', '_qbrConfig', array(
		'url'  => rest_url(QUICK_BLOCK_REORDER_REST_NAMESPACE . '/' . QUICK_BLOCK_REORDER_API_VERSION . '/blocks'),
		'restNonce' => wp_create_nonce('wp_rest')
	));

} );



