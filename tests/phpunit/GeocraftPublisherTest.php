<?php
/**
 * Tests for Geocraft_Publisher.
 */

use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;
		private $data;

		public function __construct( $code = '', $message = '', $data = null ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_code() {
			return $this->code;
		}

		public function get_error_message() {
			return $this->message;
		}

		public function get_error_data() {
			return $this->data;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $value ) {
		return $value instanceof WP_Error;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text ) {
		return $text;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action() {
		return true;
	}
}

if ( ! function_exists( 'register_rest_route' ) ) {
	function register_rest_route() {
		return true;
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		$key = strtolower( (string) $key );
		return preg_replace( '/[^a-z0-9_\-]/', '', $key );
	}
}

if ( ! class_exists( 'Geocraft_Settings' ) ) {
	class Geocraft_Settings {
		public function get_api_token() {
			return 'test-api-key';
		}

		public function get_settings() {
			return array(
				'default_status' => 'draft',
				'default_author' => 0,
			);
		}
	}
}

require_once dirname( __DIR__, 2 ) . '/includes/class-geocraft-publisher.php';

/**
 * Unit tests for endpoint auth and signature validation.
 */
class GeocraftPublisherTest extends TestCase {

	/**
	 * @var Geocraft_Publisher
	 */
	private $publisher;

	protected function setUp(): void {
		parent::setUp();
		$this->publisher = new Geocraft_Publisher();
	}

	public function test_valid_authorization_header_is_accepted() {
		$result = $this->invoke_private(
			'is_valid_authorization_header',
			array( 'Bearer test-api-key', 'test-api-key' )
		);

		$this->assertTrue( $result );
	}

	public function test_invalid_authorization_header_is_rejected() {
		$result = $this->invoke_private(
			'is_valid_authorization_header',
			array( 'Basic abc123', 'test-api-key' )
		);

		$this->assertFalse( $result );
	}

	public function test_valid_signature_with_sha256_prefix_is_accepted() {
		$body      = '{"title":"Hello"}';
		$signature = 'sha256=' . hash_hmac( 'sha256', $body, 'test-api-key' );

		$result = $this->invoke_private(
			'is_valid_signature',
			array( $signature, $body, 'test-api-key' )
		);

		$this->assertTrue( $result );
	}

	public function test_invalid_signature_is_rejected() {
		$body   = '{"title":"Hello"}';
		$result = $this->invoke_private(
			'is_valid_signature',
			array( str_repeat( 'a', 64 ), $body, 'test-api-key' )
		);

		$this->assertFalse( $result );
	}

	public function test_empty_status_falls_back_to_default_status() {
		$status = $this->invoke_private( 'sanitize_post_status', array( '' ) );
		$this->assertSame( 'draft', $status );
	}

	/**
	 * Call private method for focused unit testing.
	 *
	 * @param string $method Method name.
	 * @param array  $args   Arguments.
	 * @return mixed
	 */
	private function invoke_private( $method, array $args = array() ) {
		$ref = new ReflectionMethod( $this->publisher, $method );
		$ref->setAccessible( true );
		return $ref->invokeArgs( $this->publisher, $args );
	}
}
