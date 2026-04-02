<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package GeocraftPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/' );
}

define( 'GEOCRAFT_PLUGIN_TESTS_DIR', __DIR__ );

$geocraft_wp_tests_dir = getenv( 'WP_PHPUNIT__DIR' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

if ( $geocraft_wp_tests_dir && file_exists( $geocraft_wp_tests_dir . '/includes/bootstrap.php' ) ) {
	require_once $geocraft_wp_tests_dir . '/includes/bootstrap.php';
}
