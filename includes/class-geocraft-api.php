<?php
/**
 * GeoCraft API Client.
 *
 * Handles all HTTP communication with the GeoCraft platform API.
 * Uses the API key stored in plugin settings for authentication.
 *
 * @package GeoCraft
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class GeoCraft_API
 */
class GeoCraft_API {

	/** Base URL for the GeoCraft platform API. */
	const API_BASE = 'https://api.geocraft.io/v1';

	/** Default request timeout in seconds. */
	const TIMEOUT = 15;

	/** @var string Decrypted API key. */
	private string $api_key;

	public function __construct() {
		$settings      = new GeoCraft_Settings();
		$this->api_key = $settings->get_api_key();
	}

	// -------------------------------------------------------------------------
	// Public API methods
	// -------------------------------------------------------------------------

	/**
	 * Validate the stored API key against the GeoCraft platform.
	 *
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public function test_connection() {
		$response = $this->get( '/ping' );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		return true;
	}

	/**
	 * Publish a post to the WordPress site via the GeoCraft platform.
	 *
	 * @param array<string, mixed> $post_data Post payload from the platform.
	 * @return array<string, mixed>|\WP_Error Decoded response body or WP_Error.
	 */
	public function publish_post( array $post_data ) {
		return $this->post( '/publish', $post_data );
	}

	/**
	 * Push analytics data back to the GeoCraft platform.
	 *
	 * @param array<string, mixed> $analytics Analytics payload.
	 * @return array<string, mixed>|\WP_Error Decoded response body or WP_Error.
	 */
	public function push_analytics( array $analytics ) {
		return $this->post( '/analytics', $analytics );
	}

	// -------------------------------------------------------------------------
	// HTTP helpers
	// -------------------------------------------------------------------------

	/**
	 * Perform a GET request to the API.
	 *
	 * @param string               $endpoint Relative API endpoint (e.g. '/ping').
	 * @param array<string, mixed> $params   Optional query parameters.
	 * @return array<string, mixed>|\WP_Error Decoded JSON body or WP_Error.
	 */
	public function get( string $endpoint, array $params = array() ) {
		$url = self::API_BASE . $endpoint;
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
	 * Perform a POST request to the API.
	 *
	 * @param string               $endpoint Relative API endpoint.
	 * @param array<string, mixed> $body     Request body (will be JSON-encoded).
	 * @return array<string, mixed>|\WP_Error Decoded JSON body or WP_Error.
	 */
	public function post( string $endpoint, array $body = array() ) {
		$response = wp_remote_post(
			self::API_BASE . $endpoint,
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
	 * Build common request headers.
	 *
	 * @return array<string, string>
	 */
	private function build_headers(): array {
		return array(
			'Authorization' => 'Bearer ' . $this->api_key,
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
			'X-GeoCraft-Site' => get_bloginfo( 'url' ),
		);
	}

	/**
	 * Parse and validate an HTTP response.
	 *
	 * @param array<string, mixed>|\WP_Error $response wp_remote_* response.
	 * @return array<string, mixed>|\WP_Error Decoded body on success, WP_Error on failure.
	 */
	private function parse_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'geocraft_request_failed',
				sprintf(
					/* translators: %s: error message */
					__( 'GeoCraft API request failed: %s', 'geocraft' ),
					$response->get_error_message()
				)
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$decoded     = json_decode( $body, true );

		if ( $status_code < 200 || $status_code >= 300 ) {
			$message = isset( $decoded['message'] ) ? $decoded['message'] : $body;
			return new \WP_Error(
				'geocraft_api_error',
				sprintf(
					/* translators: 1: HTTP status code 2: error message */
					__( 'GeoCraft API returned HTTP %1$d: %2$s', 'geocraft' ),
					$status_code,
					$message
				)
			);
		}

		if ( null === $decoded && JSON_ERROR_NONE !== json_last_error() ) {
			return new \WP_Error(
				'geocraft_invalid_response',
				__( 'GeoCraft API returned an invalid JSON response.', 'geocraft' )
			);
		}

		return is_array( $decoded ) ? $decoded : array();
	}
}
