<?php
/**
 * SEO meta management for published posts.
 *
 * @package GeocraftPlugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Geocraft_SEO
 */
class Geocraft_SEO {

	/** Yoast plugin slug. */
	const PLUGIN_YOAST = 'yoast';

	/** Rank Math plugin slug. */
	const PLUGIN_RANK_MATH = 'rank_math';

	/** All in One SEO plugin slug. */
	const PLUGIN_AIOSEO = 'aioseo';

	/** Fallback plugin slug when no SEO plugin is detected. */
	const PLUGIN_NONE = 'none';

	/** Schema meta key used for all providers. */
	const META_SCHEMA_MARKUP = 'geocraft_schema_markup';

	/**
	 * Detect active SEO plugin.
	 *
	 * @return string
	 */
	public function detect_active_plugin() {
		if ( class_exists( 'WPSEO_Options' ) || defined( 'WPSEO_VERSION' ) ) {
			return self::PLUGIN_YOAST;
		}

		if ( class_exists( 'RankMath' ) || defined( 'RANK_MATH_VERSION' ) ) {
			return self::PLUGIN_RANK_MATH;
		}

		if ( class_exists( 'AIOSEO\\Plugin\\Common\\Main' ) || defined( 'AIOSEO_VERSION' ) ) {
			return self::PLUGIN_AIOSEO;
		}

		return self::PLUGIN_NONE;
	}

	/**
	 * Get post meta mapping for the active provider.
	 *
	 * @param string $plugin Plugin slug.
	 * @return array<string, string>
	 */
	public function get_meta_mapping( $plugin = '' ) {
		if ( '' === $plugin ) {
			$plugin = $this->detect_active_plugin();
		}

		switch ( $plugin ) {
			case self::PLUGIN_YOAST:
				return array(
					'seo_title'       => '_yoast_wpseo_title',
					'seo_description' => '_yoast_wpseo_metadesc',
					'seo_keywords'    => '_yoast_wpseo_focuskw',
				);
			case self::PLUGIN_RANK_MATH:
				return array(
					'seo_title'       => 'rank_math_title',
					'seo_description' => 'rank_math_description',
					'seo_keywords'    => 'rank_math_focus_keyword',
				);
			case self::PLUGIN_AIOSEO:
				return array(
					'seo_title'       => '_aioseo_title',
					'seo_description' => '_aioseo_description',
					'seo_keywords'    => '_aioseo_keywords',
				);
			default:
				return array(
					'seo_title'       => 'seo_title',
					'seo_description' => 'seo_description',
					'seo_keywords'    => 'seo_keywords',
				);
		}
	}

	/**
	 * Apply SEO metadata from publish payload to post meta.
	 *
	 * @param int                 $post_id Post ID.
	 * @param array<string, mixed> $payload Publish payload.
	 * @return void
	 */
	public function apply_seo_meta( $post_id, array $payload ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 ) {
			return;
		}

		$mapping = $this->get_meta_mapping();

		foreach ( $mapping as $payload_key => $meta_key ) {
			if ( ! array_key_exists( $payload_key, $payload ) ) {
				continue;
			}

			$value = $this->normalize_meta_value( $payload[ $payload_key ] );
			if ( '' === $value ) {
				continue;
			}

			update_post_meta( $post_id, $meta_key, $value );
		}

		if ( ! array_key_exists( 'schema_markup', $payload ) ) {
			return;
		}

		$schema = is_scalar( $payload['schema_markup'] ) ? trim( (string) $payload['schema_markup'] ) : '';
		if ( '' !== $schema ) {
			update_post_meta( $post_id, self::META_SCHEMA_MARKUP, $schema );
		}
	}

	/**
	 * Normalize SEO value into a storable string.
	 *
	 * @param mixed $value Raw payload value.
	 * @return string
	 */
	private function normalize_meta_value( $value ) {
		if ( is_array( $value ) ) {
			$value = implode(
				', ',
				array_filter(
					array_map(
						static function ( $item ) {
							return sanitize_text_field( (string) $item );
						},
						$value
					)
				)
			);
		} elseif ( is_scalar( $value ) ) {
			$value = sanitize_text_field( (string) $value );
		} else {
			$value = '';
		}

		return trim( (string) $value );
	}
}
