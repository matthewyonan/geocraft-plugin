<?php
/**
 * Tests for Geocraft_SEO.
 */

use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		$value = is_scalar( $value ) ? (string) $value : '';
		$value = strip_tags( $value );
		return trim( preg_replace( '/\s+/', ' ', $value ) );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $maybeint ) {
		return abs( (int) $maybeint );
	}
}

if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( $post_id, $meta_key, $meta_value ) {
		global $geocraft_updated_post_meta;

		if ( ! is_array( $geocraft_updated_post_meta ) ) {
			$geocraft_updated_post_meta = array();
		}

		$geocraft_updated_post_meta[] = array(
			'post_id'  => (int) $post_id,
			'meta_key' => (string) $meta_key,
			'value'    => (string) $meta_value,
		);

		return true;
	}
}

require_once dirname( __DIR__, 2 ) . '/includes/class-geocraft-seo.php';

/**
 * Unit tests for SEO plugin detection and field mapping.
 */
class GeocraftSeoTest extends TestCase {

	/**
	 * @var Geocraft_SEO
	 */
	private $seo;

	protected function setUp(): void {
		parent::setUp();
		global $geocraft_updated_post_meta;
		$geocraft_updated_post_meta = array();
		$this->seo                 = new Geocraft_SEO();
	}

	public function test_detect_active_plugin_returns_none_when_no_known_provider_is_loaded() {
		$this->assertSame( Geocraft_SEO::PLUGIN_NONE, $this->seo->detect_active_plugin() );
	}

	public function test_get_meta_mapping_returns_expected_keys_for_each_provider() {
		$this->assertSame(
			array(
				'seo_title'       => '_yoast_wpseo_title',
				'seo_description' => '_yoast_wpseo_metadesc',
				'seo_keywords'    => '_yoast_wpseo_focuskw',
			),
			$this->seo->get_meta_mapping( Geocraft_SEO::PLUGIN_YOAST )
		);

		$this->assertSame(
			array(
				'seo_title'       => 'rank_math_title',
				'seo_description' => 'rank_math_description',
				'seo_keywords'    => 'rank_math_focus_keyword',
			),
			$this->seo->get_meta_mapping( Geocraft_SEO::PLUGIN_RANK_MATH )
		);

		$this->assertSame(
			array(
				'seo_title'       => '_aioseo_title',
				'seo_description' => '_aioseo_description',
				'seo_keywords'    => '_aioseo_keywords',
			),
			$this->seo->get_meta_mapping( Geocraft_SEO::PLUGIN_AIOSEO )
		);
	}

	public function test_apply_seo_meta_stores_fallback_fields_and_schema_markup() {
		global $geocraft_updated_post_meta;

		$this->seo->apply_seo_meta(
			99,
			array(
				'seo_title'       => 'Geo Title',
				'seo_description' => 'Meta description',
				'seo_keywords'    => array( 'geo', 'content', 'ai' ),
				'schema_markup'   => '{"@context":"https://schema.org"}',
			)
		);

		$meta_by_key = array();
		foreach ( $geocraft_updated_post_meta as $meta ) {
			$meta_by_key[ $meta['meta_key'] ] = $meta['value'];
		}

		$this->assertSame( 'Geo Title', $meta_by_key['seo_title'] ?? null );
		$this->assertSame( 'Meta description', $meta_by_key['seo_description'] ?? null );
		$this->assertSame( 'geo, content, ai', $meta_by_key['seo_keywords'] ?? null );
		$this->assertSame( '{"@context":"https://schema.org"}', $meta_by_key[ Geocraft_SEO::META_SCHEMA_MARKUP ] ?? null );
	}
}
