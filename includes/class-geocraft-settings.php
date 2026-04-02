<?php
/**
 * GeoCraft Settings page.
 *
 * Registers the Settings > GeoCraft admin menu page, handles option saves with
 * nonce verification, and provides a connection-test AJAX action.
 *
 * @package GeocraftPlugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Geocraft_Settings
 */
class Geocraft_Settings {

	/** Option name (shared with Geocraft_Plugin). */
	const OPTION_KEY = 'geocraft_plugin_settings';

	/** Settings group (registered in Geocraft_Plugin::register_settings). */
	const OPTION_GROUP = 'geocraft_plugin';

	/** Nonce action for the manual settings form. */
	const NONCE_ACTION = 'geocraft_save_settings';

	/** Nonce name for the manual settings form. */
	const NONCE_FIELD = 'geocraft_nonce';

	/** Nonce action for the AJAX connection-test. */
	const TEST_NONCE_ACTION = 'geocraft_test_connection';

	/** Option name for taxonomy mappings. */
	const TAXONOMY_OPTION_KEY = 'geocraft_taxonomy_mappings';

	/** Nonce action for the AJAX category fetch from GeoCraft. */
	const FETCH_CATEGORIES_NONCE_ACTION = 'geocraft_fetch_categories';

	/** Admin page slug. */
	const PAGE_SLUG = 'geocraft-settings';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_geocraft_save_settings', array( $this, 'handle_save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_geocraft_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_geocraft_fetch_categories', array( $this, 'ajax_fetch_categories' ) );
	}

	// -------------------------------------------------------------------------
	// Admin menu
	// -------------------------------------------------------------------------

	/**
	 * Register Settings > GeoCraft sub-menu page.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_options_page(
			__( 'GeoCraft Settings', 'geocraft' ),
			__( 'GeoCraft', 'geocraft' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	// -------------------------------------------------------------------------
	// Assets
	// -------------------------------------------------------------------------

	/**
	 * Enqueue CSS and JS on the GeoCraft settings page only.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'geocraft-admin',
			plugin_dir_url( dirname( __FILE__ ) ) . 'admin/assets/css/admin.css',
			array(),
			defined( 'GEOCRAFT_VERSION' ) ? GEOCRAFT_VERSION : '0.1.0'
		);

		wp_enqueue_script(
			'geocraft-admin',
			plugin_dir_url( dirname( __FILE__ ) ) . 'admin/assets/js/admin.js',
			array( 'jquery' ),
			defined( 'GEOCRAFT_VERSION' ) ? GEOCRAFT_VERSION : '0.1.0',
			true
		);

		$wp_categories = array_map(
			function ( $cat ) {
				return array(
					'id'   => (int) $cat->term_id,
					'name' => $cat->name,
				);
			},
			get_categories( array( 'hide_empty' => false ) )
		);

		wp_localize_script(
			'geocraft-admin',
			'geocraftAdmin',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'testNonce'      => wp_create_nonce( self::TEST_NONCE_ACTION ),
				'fetchCatNonce'  => wp_create_nonce( self::FETCH_CATEGORIES_NONCE_ACTION ),
				'wpCategories'   => $wp_categories,
				'i18n'           => array(
					'testing'    => __( 'Testing…', 'geocraft' ),
					'success'    => __( 'Connection successful!', 'geocraft' ),
					'error'      => __( 'Connection failed.', 'geocraft' ),
					'loading'    => __( 'Loading…', 'geocraft' ),
					'loadError'  => __( 'Failed to load GeoCraft categories.', 'geocraft' ),
					'remove'     => __( 'Remove', 'geocraft' ),
				),
			)
		);
	}

	// -------------------------------------------------------------------------
	// Render
	// -------------------------------------------------------------------------

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'geocraft' ) );
		}

		$settings          = $this->get_settings();
		$taxonomy_mappings = $this->get_taxonomy_mappings();
		$authors           = get_users( array( 'capability' => 'publish_posts', 'fields' => array( 'ID', 'display_name' ) ) );
		$categories        = get_categories( array( 'hide_empty' => false ) );

		include dirname( __FILE__, 2 ) . '/admin/views/settings-page.php';
	}

	// -------------------------------------------------------------------------
	// Save
	// -------------------------------------------------------------------------

	/**
	 * Handle the settings form POST (action=geocraft_save_settings).
	 *
	 * @return void
	 */
	public function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'geocraft' ) );
		}

		check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD );

		$current = $this->get_settings();

		// API token — only overwrite when the user submits a real value.
		$submitted_token = isset( $_POST['geocraft_api_token'] ) ? sanitize_text_field( wp_unslash( $_POST['geocraft_api_token'] ) ) : '';
		if ( '' !== $submitted_token && '••••••••' !== $submitted_token ) {
			$current['api_token'] = $this->encrypt( $submitted_token );
		}

		// API base URL.
		$current['api_base_url'] = isset( $_POST['geocraft_api_base_url'] )
			? esc_url_raw( wp_unslash( $_POST['geocraft_api_base_url'] ) )
			: '';

		// Default post status.
		$allowed_statuses        = array( 'draft', 'publish' );
		$current['default_status'] = ( isset( $_POST['geocraft_default_status'] ) && in_array( $_POST['geocraft_default_status'], $allowed_statuses, true ) )
			? sanitize_key( $_POST['geocraft_default_status'] )
			: 'draft';

		// Default author.
		$current['default_author'] = isset( $_POST['geocraft_default_author'] )
			? absint( $_POST['geocraft_default_author'] )
			: 0;

		// Default category.
		$current['default_category'] = isset( $_POST['geocraft_default_category'] )
			? absint( $_POST['geocraft_default_category'] )
			: 0;

		update_option( self::OPTION_KEY, $current );

		// Category mappings.
		$category_map = array();
		if ( isset( $_POST['geocraft_category_map'] ) && is_array( $_POST['geocraft_category_map'] ) ) {
			foreach ( $_POST['geocraft_category_map'] as $row ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				$geo_cat = isset( $row['geocraft_cat'] ) ? sanitize_text_field( wp_unslash( $row['geocraft_cat'] ) ) : '';
				if ( '' === $geo_cat ) {
					continue;
				}
				$category_map[ $geo_cat ] = array(
					'wp_term_id'  => isset( $row['wp_term_id'] ) ? absint( $row['wp_term_id'] ) : 0,
					'auto_create' => ! empty( $row['auto_create'] ),
				);
			}
		}

		// Content type default tags.
		$content_type_tags = array();
		if ( isset( $_POST['geocraft_content_type'] ) && is_array( $_POST['geocraft_content_type'] ) ) {
			foreach ( $_POST['geocraft_content_type'] as $row ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				$type = isset( $row['type'] ) ? sanitize_key( wp_unslash( $row['type'] ) ) : '';
				if ( '' === $type ) {
					continue;
				}
				$content_type_tags[ $type ] = isset( $row['tags'] ) ? sanitize_text_field( wp_unslash( $row['tags'] ) ) : '';
			}
		}

		update_option(
			self::TAXONOMY_OPTION_KEY,
			array(
				'category_map'      => $category_map,
				'content_type_tags' => $content_type_tags,
			)
		);

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => self::PAGE_SLUG,
					'updated' => '1',
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	// -------------------------------------------------------------------------
	// AJAX
	// -------------------------------------------------------------------------

	/**
	 * AJAX handler: test the stored API key against the GeoCraft platform.
	 *
	 * @return void
	 */
	public function ajax_test_connection() {
		check_ajax_referer( self::TEST_NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'geocraft' ) ), 403 );
		}

		$api    = new Geocraft_API();
		$result = $api->test_connection();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Connection successful!', 'geocraft' ) ) );
	}

	/**
	 * AJAX handler: fetch available categories from the GeoCraft platform.
	 *
	 * @return void
	 */
	public function ajax_fetch_categories() {
		check_ajax_referer( self::FETCH_CATEGORIES_NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'geocraft' ) ), 403 );
		}

		$api    = new Geocraft_API();
		$result = $api->get( '/categories' );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'categories' => $result ) );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Retrieve stored taxonomy mappings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function get_taxonomy_mappings() {
		$defaults = array(
			'category_map'      => array(),
			'content_type_tags' => array(),
		);
		return wp_parse_args( get_option( self::TAXONOMY_OPTION_KEY, array() ), $defaults );
	}

	/**
	 * Retrieve stored settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function get_settings() {
		$defaults = array(
			'api_base_url'     => '',
			'api_token'        => '',
			'default_status'   => 'draft',
			'default_author'   => 0,
			'default_category' => 0,
		);
		return wp_parse_args( get_option( self::OPTION_KEY, array() ), $defaults );
	}

	/**
	 * Return the decrypted plain-text API token.
	 *
	 * @return string
	 */
	public function get_api_token() {
		$settings = $this->get_settings();
		return empty( $settings['api_token'] ) ? '' : $this->decrypt( $settings['api_token'] );
	}

	// -------------------------------------------------------------------------
	// Encryption (AES-256-CBC keyed from WordPress secret keys)
	// -------------------------------------------------------------------------

	/**
	 * Encrypt a plain-text value before storing in wp_options.
	 *
	 * Falls back to base64 if the openssl extension is unavailable so the value
	 * is at least not stored in plain text.
	 *
	 * @param string $plain Plain-text value.
	 * @return string Encrypted (or encoded) string.
	 */
	private function encrypt( $plain ) {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return base64_encode( $plain ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}
		$key    = substr( hash( 'sha256', AUTH_KEY . SECURE_AUTH_KEY ), 0, 32 );
		$iv     = openssl_random_pseudo_bytes( 16 );
		$cipher = openssl_encrypt( $plain, 'AES-256-CBC', $key, 0, $iv );
		return base64_encode( $iv . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypt a stored value.
	 *
	 * @param string $stored Encrypted/encoded value from wp_options.
	 * @return string Plain-text value, or empty string on failure.
	 */
	private function decrypt( $stored ) {
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return base64_decode( $stored ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		}
		$key     = substr( hash( 'sha256', AUTH_KEY . SECURE_AUTH_KEY ), 0, 32 );
		$decoded = base64_decode( $stored ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( strlen( $decoded ) < 17 ) {
			return '';
		}
		$iv    = substr( $decoded, 0, 16 );
		$data  = substr( $decoded, 16 );
		$plain = openssl_decrypt( $data, 'AES-256-CBC', $key, 0, $iv );
		return false !== $plain ? $plain : '';
	}
}
