<?php
/**
 * Plugin Name:       GeoCraft Plugin
 * Plugin URI:        https://geocraft.ai
 * Description:       WordPress integration for GeoCraft AI GEO-optimized content publishing and analytics.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            GeoCraft
 * Author URI:        https://geocraft.ai
 * Text Domain:       geocraft-plugin
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

require_once __DIR__ . '/includes/class-geocraft-plugin-activator.php';
require_once __DIR__ . '/includes/class-geocraft-plugin-deactivator.php';
require_once __DIR__ . '/includes/class-geocraft-plugin.php';

register_activation_hook( __FILE__, array( 'Geocraft_Plugin_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Geocraft_Plugin_Deactivator', 'deactivate' ) );

/**
 * Starts plugin runtime.
 *
 * @return Geocraft_Plugin
 */
function geocraft_plugin() {
	return Geocraft_Plugin::instance();
}

geocraft_plugin();
