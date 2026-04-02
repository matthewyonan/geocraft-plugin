<?php
/**
 * PHPUnit bootstrap file.
 */

define( 'GEOCRAFT_PLUGIN_TESTS_DIR', __DIR__ );

$wp_tests_dir = getenv( 'WP_PHPUNIT__DIR' );

if ( $wp_tests_dir && file_exists( $wp_tests_dir . '/includes/bootstrap.php' ) ) {
	require_once $wp_tests_dir . '/includes/bootstrap.php';
}
