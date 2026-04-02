<?php
/**
 * GeoCraft Settings page.
 *
 * Registers the Settings > GeoCraft admin page, handles option saves with
 * nonce verification, and provides a connection-test AJAX action.
 *
 * @package GeoCraft
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class GeoCraft_Settings
 */
class GeoCraft_Settings {

	/** Option key used to store all plugin settings in wp_options. */
	const OPTION_KEY = 'geocraft_settings';

	/** Nonce action for the settings form. */
	const NONCE_ACTION = 'geocraft_save_settings';

	/** Nonce action for the connection-test AJAX call. */
	const TEST_NONCE_ACTION = 'geocraft_test_connection';

	/** Settings page slug. */
	const PAGE_SLUG = 'geocraft-settings';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_geocraft_test_connection', array( $this, 'ajax_test_connection' ) );
	}

	/**
	 * Register the Settings > GeoCraft sub-menu page.
	 */
	public function register_menu(): void {
		add_options_page(
			__( 'GeoCraft Settings', 'geocraft' ),
			__( 'GeoCraft', 'geocraft' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue admin CSS only on the GeoCraft settings page.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}
		wp_enqueue_style(
			'geocraft-admin',
			GEOCRAFT_PLUGIN_URL . 'admin/assets/css/geocraft-admin.css',
			array(),
			GEOCRAFT_VERSION
		);
	}

	/**
	 * Render the settings page.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'geocraft' ) );
		}
		$settings = $this->get_settings();
		include GEOCRAFT_PLUGIN_DIR . 'admin/views/settings-page.php';
	}

	/**
	 * Handle the settings form submission.
	 *
	 * Runs on admin_init so redirects work correctly.
	 */
	public function handle_save(): void {
		if ( ! isset( $_POST['geocraft_save_settings'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'geocraft' ) );
		}

		check_admin_referer( self::NONCE_ACTION, 'geocraft_nonce' );

		$current  = $this->get_settings();
		$new_key  = isset( $_POST['geocraft_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['geocraft_api_key'] ) ) : '';

		// Only update the stored key if the user actually changed it (non-placeholder value).
		if ( '' !== $new_key && '••••••••' !== $new_key ) {
			$current['api_key'] = $this->encrypt_api_key( $new_key );
		}

		$current['default_status']   = in_array( $_POST['geocraft_default_status'] ?? '', array( 'draft', 'publish' ), true )
			? sanitize_key( $_POST['geocraft_default_status'] )
			: 'draft';
		$current['default_author']   = absint( $_POST['geocraft_default_author'] ?? 0 );
		$current['default_category'] = absint( $_POST['geocraft_default_category'] ?? 0 );

		update_option( self::OPTION_KEY, $current );

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => self::PAGE_SLUG, 'updated' => '1' ),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	/**
	 * AJAX: test the stored API key by calling the GeoCraft platform.
	 */
	public function ajax_test_connection(): void {
		check_ajax_referer( self::TEST_NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'geocraft' ) ), 403 );
		}

		$api      = new GeoCraft_API();
		$result   = $api->test_connection();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Connection successful!', 'geocraft' ) ) );
	}

	/**
	 * Retrieve plugin settings, merging with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function get_settings(): array {
		$defaults = array(
			'api_key'          => '',
			'default_status'   => 'draft',
			'default_author'   => 0,
			'default_category' => 0,
		);
		$stored = get_option( self::OPTION_KEY, array() );
		return wp_parse_args( $stored, $defaults );
	}

	/**
	 * Return the decrypted API key, or an empty string if none is set.
	 *
	 * @return string
	 */
	public function get_api_key(): string {
		$settings = $this->get_settings();
		if ( empty( $settings['api_key'] ) ) {
			return '';
		}
		return $this->decrypt_api_key( $settings['api_key'] );
	}

	// -------------------------------------------------------------------------
	// Encryption helpers (lightweight — uses WP AUTH_KEY as the secret).
	// -------------------------------------------------------------------------

	/**
	 * Encrypt a plain-text API key before storing it in wp_options.
	 *
	 * Uses openssl_encrypt with AES-256-CBC when the openssl extension is
	 * available; falls back to base64 so the value is at least not stored in
	 * plain text in case openssl is unavailable.
	 *
	 * @param string $plain_key Plain-text API key.
	 * @return string Encrypted (or encoded) value.
	 */
	private function encrypt_api_key( string $plain_key ): string {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return base64_encode( $plain_key ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}
		$secret = substr( hash( 'sha256', AUTH_KEY . SECURE_AUTH_KEY ), 0, 32 );
		$iv     = openssl_random_pseudo_bytes( 16 );
		$cipher = openssl_encrypt( $plain_key, 'AES-256-CBC', $secret, 0, $iv );
		return base64_encode( $iv . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypt a stored API key value.
	 *
	 * @param string $stored_key Encrypted/encoded value from wp_options.
	 * @return string Plain-text API key.
	 */
	private function decrypt_api_key( string $stored_key ): string {
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return base64_decode( $stored_key ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		}
		$secret   = substr( hash( 'sha256', AUTH_KEY . SECURE_AUTH_KEY ), 0, 32 );
		$decoded  = base64_decode( $stored_key ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( strlen( $decoded ) < 17 ) {
			return '';
		}
		$iv     = substr( $decoded, 0, 16 );
		$cipher = substr( $decoded, 16 );
		$plain  = openssl_decrypt( $cipher, 'AES-256-CBC', $secret, 0, $iv );
		return false !== $plain ? $plain : '';
	}
}
