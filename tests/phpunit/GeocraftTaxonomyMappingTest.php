<?php
/**
 * Tests for taxonomy mapping logic (category mapping & content-type default tags).
 */

use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

// ---------------------------------------------------------------------------
// WordPress stub functions and classes (only declared when not already present)
// ---------------------------------------------------------------------------

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
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( strip_tags( (string) $str ) );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $v ) {
		return abs( (int) $v );
	}
}

// ---------------------------------------------------------------------------
// Configurable stubs shared across tests
// ---------------------------------------------------------------------------

/** Tracks calls made to wp_set_post_terms(). */
$GLOBALS['wp_set_post_terms_calls'] = array();

if ( ! function_exists( 'wp_set_post_terms' ) ) {
	function wp_set_post_terms( $post_id, $terms, $taxonomy, $append ) {
		$GLOBALS['wp_set_post_terms_calls'][] = compact( 'post_id', 'terms', 'taxonomy', 'append' );
		return array();
	}
}

/** Controls what term_exists() returns (set per test). */
$GLOBALS['term_exists_return'] = null;

if ( ! function_exists( 'term_exists' ) ) {
	function term_exists( $term, $taxonomy ) {
		return $GLOBALS['term_exists_return'];
	}
}

/** Controls what wp_insert_term() returns (set per test). */
$GLOBALS['wp_insert_term_return'] = null;

if ( ! function_exists( 'wp_insert_term' ) ) {
	function wp_insert_term( $term, $taxonomy, $args = array() ) {
		return $GLOBALS['wp_insert_term_return'];
	}
}

// ---------------------------------------------------------------------------
// Geocraft_Settings stub with configurable taxonomy mappings
// ---------------------------------------------------------------------------

if ( ! class_exists( 'Geocraft_Settings' ) ) {
	class Geocraft_Settings {
		/** @var array */
		public static $taxonomy_mappings = array(
			'category_map'      => array(),
			'content_type_tags' => array(),
		);

		public function get_api_token() {
			return 'test-api-key';
		}

		public function get_settings() {
			return array(
				'default_status' => 'draft',
				'default_author' => 0,
			);
		}

		public function get_taxonomy_mappings() {
			return self::$taxonomy_mappings;
		}
	}
}

// ---------------------------------------------------------------------------
// Geocraft_SEO stub
// ---------------------------------------------------------------------------

if ( ! class_exists( 'Geocraft_SEO' ) ) {
	class Geocraft_SEO {
		public function apply_seo_meta( $post_id, $payload ) {
			// no-op in tests
		}
	}
}

require_once dirname( __DIR__, 2 ) . '/includes/class-geocraft-publisher.php';

// ---------------------------------------------------------------------------
// Test class
// ---------------------------------------------------------------------------

/**
 * Unit tests for taxonomy mapping (category mapping & content-type tags).
 */
class GeocraftTaxonomyMappingTest extends TestCase {

	/** @var Geocraft_Publisher */
	private $publisher;

	protected function setUp(): void {
		parent::setUp();
		$this->publisher = new Geocraft_Publisher();

		// Reset tracking globals before each test.
		$GLOBALS['wp_set_post_terms_calls'] = array();
		$GLOBALS['term_exists_return']      = null;
		$GLOBALS['wp_insert_term_return']   = null;

		// Reset mappings to empty defaults.
		Geocraft_Settings::$taxonomy_mappings = array(
			'category_map'      => array(),
			'content_type_tags' => array(),
		);
	}

	// -------------------------------------------------------------------------
	// apply_category_mapping
	// -------------------------------------------------------------------------

	public function test_category_mapping_assigns_wp_term_when_term_id_configured() {
		Geocraft_Settings::$taxonomy_mappings['category_map']['technology'] = array(
			'wp_term_id'  => 7,
			'auto_create' => false,
		);

		$this->invoke_private( 'apply_category_mapping', array( 1, 'technology' ) );

		$calls = $GLOBALS['wp_set_post_terms_calls'];
		$this->assertCount( 1, $calls );
		$this->assertSame( array( 7 ), $calls[0]['terms'] );
		$this->assertSame( 'category', $calls[0]['taxonomy'] );
	}

	public function test_category_mapping_skipped_when_no_mapping_exists() {
		// No mapping configured — nothing should be assigned.
		$this->invoke_private( 'apply_category_mapping', array( 1, 'unknown_cat' ) );

		$this->assertEmpty( $GLOBALS['wp_set_post_terms_calls'] );
	}

	public function test_category_mapping_auto_creates_term_when_not_found() {
		Geocraft_Settings::$taxonomy_mappings['category_map']['travel'] = array(
			'wp_term_id'  => 0,
			'auto_create' => true,
		);

		// term_exists returns null (not found), wp_insert_term returns a new term.
		$GLOBALS['term_exists_return']    = null;
		$GLOBALS['wp_insert_term_return'] = array( 'term_id' => 42, 'term_taxonomy_id' => 1 );

		$this->invoke_private( 'apply_category_mapping', array( 1, 'travel' ) );

		$calls = $GLOBALS['wp_set_post_terms_calls'];
		$this->assertCount( 1, $calls );
		$this->assertSame( array( 42 ), $calls[0]['terms'] );
	}

	public function test_category_mapping_uses_existing_term_when_auto_create_set() {
		Geocraft_Settings::$taxonomy_mappings['category_map']['travel'] = array(
			'wp_term_id'  => 0,
			'auto_create' => true,
		);

		// term_exists returns the existing term.
		$GLOBALS['term_exists_return'] = array( 'term_id' => 15, 'term_taxonomy_id' => 2 );

		$this->invoke_private( 'apply_category_mapping', array( 1, 'travel' ) );

		$calls = $GLOBALS['wp_set_post_terms_calls'];
		$this->assertCount( 1, $calls );
		$this->assertSame( array( 15 ), $calls[0]['terms'] );
	}

	public function test_category_mapping_skipped_when_auto_create_false_and_no_term_id() {
		Geocraft_Settings::$taxonomy_mappings['category_map']['travel'] = array(
			'wp_term_id'  => 0,
			'auto_create' => false,
		);

		$this->invoke_private( 'apply_category_mapping', array( 1, 'travel' ) );

		$this->assertEmpty( $GLOBALS['wp_set_post_terms_calls'] );
	}

	// -------------------------------------------------------------------------
	// apply_content_type_tags
	// -------------------------------------------------------------------------

	public function test_content_type_tags_assigns_tags_from_config() {
		Geocraft_Settings::$taxonomy_mappings['content_type_tags']['blog_post'] = 'wordpress, tutorial';

		$this->invoke_private( 'apply_content_type_tags', array( 1, 'blog_post' ) );

		$calls = $GLOBALS['wp_set_post_terms_calls'];
		$this->assertCount( 1, $calls );
		$this->assertSame( array( 'wordpress', 'tutorial' ), $calls[0]['terms'] );
		$this->assertSame( 'post_tag', $calls[0]['taxonomy'] );
	}

	public function test_content_type_tags_trims_whitespace_around_tags() {
		Geocraft_Settings::$taxonomy_mappings['content_type_tags']['news'] = '  breaking ,  updates  ';

		$this->invoke_private( 'apply_content_type_tags', array( 1, 'news' ) );

		$calls = $GLOBALS['wp_set_post_terms_calls'];
		$this->assertSame( array( 'breaking', 'updates' ), $calls[0]['terms'] );
	}

	public function test_content_type_tags_skipped_when_no_mapping_exists() {
		$this->invoke_private( 'apply_content_type_tags', array( 1, 'unknown_type' ) );

		$this->assertEmpty( $GLOBALS['wp_set_post_terms_calls'] );
	}

	public function test_content_type_tags_skipped_when_mapping_is_empty_string() {
		Geocraft_Settings::$taxonomy_mappings['content_type_tags']['empty_type'] = '';

		$this->invoke_private( 'apply_content_type_tags', array( 1, 'empty_type' ) );

		$this->assertEmpty( $GLOBALS['wp_set_post_terms_calls'] );
	}

	// -------------------------------------------------------------------------
	// Geocraft_Settings::get_taxonomy_mappings defaults
	// -------------------------------------------------------------------------

	public function test_get_taxonomy_mappings_returns_defaults_when_empty() {
		Geocraft_Settings::$taxonomy_mappings = array(
			'category_map'      => array(),
			'content_type_tags' => array(),
		);

		$settings = new Geocraft_Settings();
		$mappings = $settings->get_taxonomy_mappings();

		$this->assertArrayHasKey( 'category_map', $mappings );
		$this->assertArrayHasKey( 'content_type_tags', $mappings );
		$this->assertIsArray( $mappings['category_map'] );
		$this->assertIsArray( $mappings['content_type_tags'] );
	}

	// -------------------------------------------------------------------------
	// Helper
	// -------------------------------------------------------------------------

	/**
	 * Invoke a private method via reflection.
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
