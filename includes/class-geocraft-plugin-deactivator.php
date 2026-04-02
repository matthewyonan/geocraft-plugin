<?php
/**
 * Deactivation hook routines.
 *
 * @package GeocraftPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles deactivation tasks.
 */
class Geocraft_Plugin_Deactivator {

	/**
	 * Runs deactivation routines.
	 *
	 * @return void
	 */
	public static function deactivate() {
		// Intentionally keep persistent settings on deactivation.
	}
}
