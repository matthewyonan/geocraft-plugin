<?php
/**
 * Activation hook routines.
 *
 * @package GeocraftPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles activation tasks.
 */
class Geocraft_Plugin_Activator {

	/**
	 * Runs activation routines.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( false === get_option( 'geocraft_plugin_settings', false ) ) {
			add_option(
				'geocraft_plugin_settings',
				array(
					'api_base_url' => '',
					'api_token'    => '',
				)
			);
		}
	}
}
