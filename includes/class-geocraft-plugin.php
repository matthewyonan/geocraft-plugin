<?php
/**
 * Main plugin runtime class.
 *
 * @package GeocraftPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GeoCraft plugin runtime.
 */
class Geocraft_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Geocraft_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Option name for plugin settings.
	 *
	 * @var string
	 */
	private $option_name = 'geocraft_plugin_settings';

	/**
	 * Returns singleton instance.
	 *
	 * @return Geocraft_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->load_includes();
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		new Geocraft_Publisher();
		new Geocraft_Analytics();
		if ( is_admin() ) {
			new Geocraft_Settings();
		}
	}

	/**
	 * Require sub-system class files.
	 *
	 * @return void
	 */
	private function load_includes() {
		$files = array(
			'class-geocraft-api.php',
			'class-geocraft-publisher.php',
			'class-geocraft-settings.php',
			'class-geocraft-analytics.php',
			'class-geocraft-seo.php',
		);
		foreach ( $files as $file ) {
			require_once __DIR__ . '/' . $file;
		}
	}

	/**
	 * Loads translation files.
	 *
	 * Since WordPress 4.6+, translations for plugins hosted on WordPress.org
	 * are loaded automatically. This is retained only for custom translation
	 * files shipped directly with the plugin.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		// Automatic translations are handled by WordPress 4.6+ for wp.org hosted plugins.
		// Only load custom translation files if they exist in the languages directory.
		$mofile = dirname( plugin_basename( __FILE__ ) ) . '/../languages/geocraft-plugin-' . determine_locale() . '.mo';
		if ( file_exists( WP_PLUGIN_DIR . '/' . $mofile ) ) {
			load_textdomain( 'geocraft-plugin', WP_PLUGIN_DIR . '/' . $mofile );
		}
	}

	/**
	 * Registers plugin settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'geocraft_plugin',
			$this->option_name,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array(
					'api_base_url'     => '',
					'api_token'        => '',
					'default_status'   => 'draft',
					'default_author'   => 0,
					'default_category' => 0,
				),
			)
		);
	}

	/**
	 * Sanitizes plugin settings.
	 *
	 * @param array<string, mixed> $settings Raw submitted settings.
	 *
	 * @return array<string, string>
	 */
	public function sanitize_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();

		$allowed_statuses = array( 'draft', 'publish' );

		return array(
			'api_base_url'     => isset( $settings['api_base_url'] ) ? esc_url_raw( wp_unslash( $settings['api_base_url'] ) ) : '',
			'api_token'        => isset( $settings['api_token'] ) ? sanitize_text_field( wp_unslash( $settings['api_token'] ) ) : '',
			'default_status'   => ( isset( $settings['default_status'] ) && in_array( $settings['default_status'], $allowed_statuses, true ) ) ? $settings['default_status'] : 'draft',
			'default_author'   => isset( $settings['default_author'] ) ? absint( $settings['default_author'] ) : 0,
			'default_category' => isset( $settings['default_category'] ) ? absint( $settings['default_category'] ) : 0,
		);
	}
}
