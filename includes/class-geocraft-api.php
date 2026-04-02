<?php
/**
 * GeoCraft API Client.
 *
 * Handles all HTTP communication with the GeoCraft platform API.
 * Reads the stored API token and base URL from plugin settings.
 *
 * @package GeocraftPlugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Geocraft_API
 */
class Geocraft_API {

	/** Fallback base URL when none is configured. */
	const DEFAULT_API_BASE = 'https://api.geocraft.ai/v1';

	/** Request timeout in seconds. */
	const TIMEOUT = 15;

	/** @var string Decrypted API token. */
	private $api_token;

	/** @var string API base URL. */
	private $api_base;

	public function __construct() {
		$settings        = new Geocraft_Settings();
		$this->api_token = $settings->get_api_token();
		$stored_base     = $settings->get_settings()['api_base_url'] ?? '';
		$this->api_base  = '' !== $stored_base ? rtrim( $stored_base, '/' ) : self::DEFAULT_API_BASE;
	}

	// -------------------------------------------------------------------------
	// Public API surface
	// -------------------------------------------------------------------------

	/**
	 * Validate the stored API token against the GeoCraft platform.
	 *
	 * @return true|\WP_Error True on success.
	 */
	public function test_connection() {
		$response = $this->get( '/ping' );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		return true;
	}

	/**
	 * Publish a post payload via the GeoCraft platform.
	 *
	 * @param array<string, mixed> $post_data Post payload.
	 * @return array<string, mixed>|\WP_Error Decoded response or WP_Error.
	 */
	public function publish_post( array $post_data ) {
		return $this->post( '/publish', $post_data );
	}

	/**
	 * Push analytics data to the GeoCraft platform.
	 *
	 * @param array<string, mixed> $analytics Analytics payload.
	 * @return array<string, mixed>|\WP_Error Decoded response or WP_Error.
	 */
	public function push_analytics( array $analytics ) {
		return $this->post( '/analytics', $analytics );
	}

	// -------------------------------------------------------------------------
	// HTTP helpers
	// -------------------------------------------------------------------------

	/**
	 * Perform a GET request.
	 *
	 * @param string               $endpoint Relative endpoint (e.g. '/ping').
	 * @param array<string, mixed> $params   Optional query parameters.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function get( $endpoint, array $params = array() ) {
		$url = $this->api_base . $endpoint;
		if ( ! empty( $params ) ) {
			$url = add_query_arg( $params, $url );
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => self::TIMEOUT,
				'headers' => $this->build_headers(),
			)
		);

		return $this->parse_response( $response );
	}

	/**
	 * Perform a POST request.
	 *
	 * @param string               $endpoint Relative endpoint.
	 * @param array<string, mixed> $body     Request body (JSON-encoded).
	 * @return array<string, mixed>|\WP_Error
	 */
	public function post( $endpoint, array $body = array() ) {
		$response = wp_remote_post(
			$this->api_base . $endpoint,
			array(
				'timeout' => self::TIMEOUT,
				'headers' => $this->build_headers(),
				'body'    => wp_json_encode( $body ),
			)
		);

		return $this->parse_response( $response );
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Build common request headers including Authorization.
	 *
	 * @return array<string, string>
	 */
	private function build_headers() {
		return array(
			'Authorization'   => 'Bearer ' . $this->api_token,
			'Content-Type'    => 'application/json',
			'Accept'          => 'application/json',
			'X-GeoCraft-Site' => get_bloginfo( 'url' ),
		);
	}

	/**
	 * Parse and validate an HTTP response.
	 *
	 * @param array<string, mixed>|\WP_Error $response wp_remote_* response.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function parse_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'geocraft_request_failed',
				sprintf(
					/* translators: %s: underlying error message */
					__( 'GeoCraft API request failed: %s', 'geocraft-plugin' ),
					$response->get_error_message()
				)
			);
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$decoded     = json_decode( $body, true );

		if ( $status_code < 200 || $status_code >= 300 ) {
			$message = ( is_array( $decoded ) && isset( $decoded['message'] ) ) ? $decoded['message'] : $body;
			return new WP_Error(
				'geocraft_api_error',
				sprintf(
					/* translators: 1: HTTP status code 2: error detail */
					__( 'GeoCraft API returned HTTP %1$d: %2$s', 'geocraft-plugin' ),
					$status_code,
					$message
				)
			);
		}

		if ( null === $decoded && JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error(
				'geocraft_invalid_response',
				__( 'GeoCraft API returned an invalid JSON response.', 'geocraft-plugin' )
			);
		}

		return is_array( $decoded ) ? $decoded : array();
	}
}
