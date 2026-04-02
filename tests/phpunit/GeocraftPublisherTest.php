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

if ( ! class_exists( 'Geocraft_SEO' ) ) {
	class Geocraft_SEO {
		public function apply_seo_meta( $post_id, $payload ) {
			// no-op stub.
		}
	}
}

/**
 * Minimal REST request stub for unit testing bulk/publish handlers.
 */
class MockRestRequest {
	/** @var mixed */
	private $json_params;

	/**
	 * @param mixed $json_params Value returned by get_json_params().
	 */
	public function __construct( $json_params ) {
		$this->json_params = $json_params;
	}

	public function get_json_params() {
		return $this->json_params;
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

	// -------------------------------------------------------------------------
	// Bulk publish endpoint — validation tests
	// -------------------------------------------------------------------------

	public function test_bulk_publish_rejects_null_body() {
		$request = new MockRestRequest( null );
		$result  = $this->publisher->handle_bulk_publish( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'geocraft_invalid_payload', $result->get_error_code() );
	}

	public function test_bulk_publish_rejects_string_body() {
		$request = new MockRestRequest( 'not-an-array' );
		$result  = $this->publisher->handle_bulk_publish( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'geocraft_invalid_payload', $result->get_error_code() );
	}

	public function test_bulk_publish_rejects_associative_object_body() {
		// A single post object sent to the bulk endpoint should be rejected.
		$request = new MockRestRequest( array( 'title' => 'Hello', 'body' => 'World', 'post_status' => 'draft' ) );
		$result  = $this->publisher->handle_bulk_publish( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'geocraft_invalid_payload', $result->get_error_code() );
	}

	public function test_bulk_publish_rejects_more_than_50_items() {
		$payloads = array_fill( 0, 51, array( 'title' => 'T', 'body' => 'B', 'post_status' => 'draft' ) );
		$request  = new MockRestRequest( $payloads );
		$result   = $this->publisher->handle_bulk_publish( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'geocraft_bulk_limit_exceeded', $result->get_error_code() );
	}

	public function test_bulk_publish_accepts_exactly_50_items_without_limit_error() {
		// Exactly 50 items should pass the limit check (individual items will fail
		// later due to missing WP functions, but the limit error must NOT fire).
		$payloads = array_fill( 0, 50, array( 'title' => 'T', 'body' => 'B', 'post_status' => 'draft' ) );
		$request  = new MockRestRequest( $payloads );
		$result   = $this->publisher->handle_bulk_publish( $request );

		// The limit guard must not return a WP_Error for exactly 50 items.
		if ( is_wp_error( $result ) ) {
			$this->assertNotSame( 'geocraft_bulk_limit_exceeded', $result->get_error_code() );
		} else {
			// If WP functions are not available the result will still be a response object.
			$this->assertNotInstanceOf( WP_Error::class, $result );
		}
	}

	public function test_bulk_publish_returns_empty_array_for_empty_input() {
		$request = new MockRestRequest( array() );
		$result  = $this->publisher->handle_bulk_publish( $request );

		// An empty payload list is valid; no items to process.
		$this->assertNotInstanceOf( WP_Error::class, $result );
	}

	public function test_bulk_publish_records_failure_for_non_array_item() {
		// A list containing a non-object entry should record a per-item error.
		$request = new MockRestRequest( array( 'not-an-object' ) );
		$result  = $this->publisher->handle_bulk_publish( $request );

		$this->assertNotInstanceOf( WP_Error::class, $result );
		// Result is a WP_REST_Response — inspect the data property directly.
		$data = $result->data ?? null;
		$this->assertIsArray( $data );
		$this->assertCount( 1, $data );
		$this->assertFalse( $data[0]['success'] );
		$this->assertSame( 'geocraft_invalid_item', $data[0]['error']['code'] );
	}

	public function test_bulk_max_items_constant_is_50() {
		$this->assertSame( 50, Geocraft_Publisher::BULK_MAX_ITEMS );
	}

	public function test_bulk_route_constant_is_publish_bulk() {
		$this->assertSame( '/publish/bulk', Geocraft_Publisher::REST_ROUTE_BULK );
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
