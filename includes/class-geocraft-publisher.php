<?php
/**
 * GeoCraft Publisher REST endpoint.
 *
 * @package GeocraftPlugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Geocraft_Publisher
 */
class Geocraft_Publisher {

	/** REST namespace. */
	const REST_NAMESPACE = 'geocraft/v1';

	/** REST route path. */
	const REST_ROUTE = '/publish';

	/** Post meta key used to map remote GeoCraft post IDs. */
	const META_POST_ID = 'geocraft_post_id';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register GeoCraft publishing route.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_publish' ),
				'permission_callback' => array( $this, 'authorize_request' ),
			)
		);
	}

	/**
	 * Permission callback: validates API key and request signature.
	 *
	 * @param mixed $request REST request object.
	 * @return true|WP_Error
	 */
	public function authorize_request( $request ) {
		$api_key = $this->get_api_key();
		if ( '' === $api_key ) {
			return new WP_Error(
				'geocraft_auth_not_configured',
				__( 'GeoCraft API key is not configured.', 'geocraft-plugin' ),
				array( 'status' => 500 )
			);
		}

		$auth_header = method_exists( $request, 'get_header' ) ? (string) $request->get_header( 'authorization' ) : '';
		if ( ! $this->is_valid_authorization_header( $auth_header, $api_key ) ) {
			return new WP_Error(
				'geocraft_invalid_api_key',
				__( 'Invalid Authorization header.', 'geocraft-plugin' ),
				array( 'status' => 401 )
			);
		}

		$signature_header = method_exists( $request, 'get_header' ) ? (string) $request->get_header( 'x-geocraft-signature' ) : '';
		$raw_body         = method_exists( $request, 'get_body' ) ? (string) $request->get_body() : '';

		if ( ! $this->is_valid_signature( $signature_header, $raw_body, $api_key ) ) {
			return new WP_Error(
				'geocraft_invalid_signature',
				__( 'Invalid webhook signature.', 'geocraft-plugin' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * Handle publish endpoint request.
	 *
	 * @param mixed $request REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_publish( $request ) {
		$payload = method_exists( $request, 'get_json_params' ) ? $request->get_json_params() : null;
		if ( ! is_array( $payload ) ) {
			return new WP_Error(
				'geocraft_invalid_payload',
				__( 'Request body must be valid JSON.', 'geocraft-plugin' ),
				array( 'status' => 400 )
			);
		}

		$prepared = $this->prepare_post_args( $payload );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		$remote_post_id = isset( $payload['geocraft_post_id'] ) ? sanitize_text_field( (string) $payload['geocraft_post_id'] ) : '';
		$wp_post_id     = $this->find_existing_post_id( $remote_post_id );
		$operation      = $wp_post_id > 0 ? 'updated' : 'created';

		if ( $wp_post_id > 0 ) {
			$prepared['ID'] = $wp_post_id;
			$result         = wp_update_post( wp_slash( $prepared ), true, false );
		} else {
			$result = wp_insert_post( wp_slash( $prepared ), true, false );
		}

		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				'geocraft_publish_failed',
				sprintf(
					/* translators: %s: error message */
					__( 'Post publish failed: %s', 'geocraft-plugin' ),
					$result->get_error_message()
				),
				array( 'status' => 500 )
			);
		}

		$wp_post_id = (int) $result;

		if ( '' !== $remote_post_id ) {
			update_post_meta( $wp_post_id, self::META_POST_ID, $remote_post_id );
		}

		if ( ! empty( $payload['categories'] ) ) {
			$this->assign_categories( $wp_post_id, $payload['categories'] );
		} else {
			$geo_category = isset( $payload['geocraft_category'] ) ? sanitize_text_field( (string) $payload['geocraft_category'] ) : '';
			if ( '' !== $geo_category ) {
				$this->apply_category_mapping( $wp_post_id, $geo_category );
			}
		}

		if ( ! empty( $payload['tags'] ) ) {
			$this->assign_tags( $wp_post_id, $payload['tags'] );
		} else {
			$content_type = isset( $payload['content_type'] ) ? sanitize_text_field( (string) $payload['content_type'] ) : '';
			if ( '' !== $content_type ) {
				$this->apply_content_type_tags( $wp_post_id, $content_type );
			}
		}

		if ( ! empty( $payload['featured_image_url'] ) ) {
			$featured_image = $this->attach_featured_image( $wp_post_id, (string) $payload['featured_image_url'] );
			if ( is_wp_error( $featured_image ) ) {
				return $featured_image;
			}
		}

		$response = new WP_REST_Response(
			array(
				'post_id'   => $wp_post_id,
				'permalink' => get_permalink( $wp_post_id ),
				'status'    => get_post_status( $wp_post_id ),
				'operation' => $operation,
			),
			'created' === $operation ? 201 : 200
		);

		return $response;
	}

	/**
	 * Build and validate post args from request payload.
	 *
	 * @param array<string, mixed> $payload Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	private function prepare_post_args( array $payload ) {
		$title = isset( $payload['title'] ) ? sanitize_text_field( (string) $payload['title'] ) : '';
		$body  = isset( $payload['body'] ) ? wp_kses_post( (string) $payload['body'] ) : '';

		if ( '' === $title || '' === $body ) {
			return new WP_Error(
				'geocraft_missing_required_fields',
				__( 'Both title and body are required.', 'geocraft-plugin' ),
				array( 'status' => 400 )
			);
		}

		$status = $this->sanitize_post_status( isset( $payload['post_status'] ) ? (string) $payload['post_status'] : '' );
		if ( '' === $status ) {
			return new WP_Error(
				'geocraft_invalid_status',
				__( 'Invalid post status provided.', 'geocraft-plugin' ),
				array( 'status' => 400 )
			);
		}

		$post_args = array(
			'post_title'   => $title,
			'post_content' => $body,
			'post_excerpt' => isset( $payload['excerpt'] ) ? wp_kses_post( (string) $payload['excerpt'] ) : '',
			'post_status'  => $status,
			'post_type'    => 'post',
		);

		$author_id = $this->resolve_author_id();
		if ( $author_id > 0 ) {
			$post_args['post_author'] = $author_id;
		}

		if ( ! empty( $payload['publish_date'] ) ) {
			$publish_date = $this->normalize_publish_date( (string) $payload['publish_date'] );
			if ( is_wp_error( $publish_date ) ) {
				return $publish_date;
			}

			$post_args['post_date']     = $publish_date['post_date'];
			$post_args['post_date_gmt'] = $publish_date['post_date_gmt'];

			if ( 'publish' === $post_args['post_status'] && strtotime( $publish_date['post_date_gmt'] ) > time() ) {
				$post_args['post_status'] = 'future';
			}
		}

		return $post_args;
	}

	/**
	 * Resolve configured API key.
	 *
	 * @return string
	 */
	private function get_api_key() {
		$settings = new Geocraft_Settings();
		$api_key  = $settings->get_api_token();
		return is_string( $api_key ) ? trim( $api_key ) : '';
	}

	/**
	 * Validate Bearer token header.
	 *
	 * @param string $header  Authorization header value.
	 * @param string $api_key Stored API key.
	 * @return bool
	 */
	private function is_valid_authorization_header( $header, $api_key ) {
		if ( ! preg_match( '/^Bearer\s+(.+)$/i', trim( $header ), $matches ) ) {
			return false;
		}

		$provided = trim( $matches[1] );
		return '' !== $provided && hash_equals( $api_key, $provided );
	}

	/**
	 * Validate HMAC-SHA256 signature.
	 *
	 * Supports both "sha256=<hash>" and "<hash>" header formats.
	 *
	 * @param string $signature_header Signature header.
	 * @param string $body             Raw request body.
	 * @param string $api_key          HMAC key.
	 * @return bool
	 */
	private function is_valid_signature( $signature_header, $body, $api_key ) {
		$signature = trim( $signature_header );
		if ( '' === $signature ) {
			return false;
		}

		if ( 0 === stripos( $signature, 'sha256=' ) ) {
			$signature = substr( $signature, 7 );
		}

		if ( ! preg_match( '/^[a-f0-9]{64}$/i', $signature ) ) {
			return false;
		}

		$expected = hash_hmac( 'sha256', $body, $api_key );
		return hash_equals( strtolower( $expected ), strtolower( $signature ) );
	}

	/**
	 * Find an existing post by remote GeoCraft ID.
	 *
	 * @param string $remote_post_id GeoCraft post ID.
	 * @return int
	 */
	private function find_existing_post_id( $remote_post_id ) {
		if ( '' === $remote_post_id ) {
			return 0;
		}

		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => self::META_POST_ID, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $remote_post_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		if ( empty( $posts ) ) {
			return 0;
		}

		return (int) $posts[0];
	}

	/**
	 * Sanitize incoming post status.
	 *
	 * @param string $status Requested status.
	 * @return string
	 */
	private function sanitize_post_status( $status ) {
		$allowed = array( 'draft', 'publish', 'future', 'pending', 'private' );
		$status  = sanitize_key( $status );

		if ( '' === $status ) {
			$settings = new Geocraft_Settings();
			$stored   = $settings->get_settings();
			$status   = isset( $stored['default_status'] ) ? sanitize_key( (string) $stored['default_status'] ) : 'draft';
		}

		if ( ! in_array( $status, $allowed, true ) ) {
			return '';
		}

		return $status;
	}

	/**
	 * Resolve the configured default author.
	 *
	 * @return int
	 */
	private function resolve_author_id() {
		$settings  = new Geocraft_Settings();
		$stored    = $settings->get_settings();
		$author_id = isset( $stored['default_author'] ) ? absint( $stored['default_author'] ) : 0;

		if ( $author_id > 0 && get_user_by( 'id', $author_id ) ) {
			return $author_id;
		}

		return 0;
	}

	/**
	 * Normalize publish date string into WP post date fields.
	 *
	 * @param string $publish_date Input date string.
	 * @return array<string, string>|WP_Error
	 */
	private function normalize_publish_date( $publish_date ) {
		$timestamp = strtotime( $publish_date );
		if ( false === $timestamp ) {
			return new WP_Error(
				'geocraft_invalid_publish_date',
				__( 'publish_date is invalid.', 'geocraft-plugin' ),
				array( 'status' => 400 )
			);
		}

		$post_date_gmt = gmdate( 'Y-m-d H:i:s', $timestamp );
		$post_date     = get_date_from_gmt( $post_date_gmt, 'Y-m-d H:i:s' );

		return array(
			'post_date'     => $post_date,
			'post_date_gmt' => $post_date_gmt,
		);
	}

	/**
	 * Assign category terms by name and/or IDs.
	 *
	 * @param int   $post_id    Post ID.
	 * @param mixed $categories Category list.
	 * @return void
	 */
	private function assign_categories( $post_id, $categories ) {
		$category_ids = array();
		$items        = is_array( $categories ) ? $categories : array( $categories );

		foreach ( $items as $item ) {
			if ( is_numeric( $item ) ) {
				$category_ids[] = absint( $item );
				continue;
			}

			$name = sanitize_text_field( (string) $item );
			if ( '' === $name ) {
				continue;
			}

			$term = term_exists( $name, 'category' );
			if ( ! $term ) {
				$term = wp_insert_term( $name, 'category' );
			}

			if ( is_wp_error( $term ) ) {
				continue;
			}

			$term_id = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
			if ( $term_id > 0 ) {
				$category_ids[] = $term_id;
			}
		}

		$category_ids = array_values( array_unique( array_filter( array_map( 'absint', $category_ids ) ) ) );
		if ( ! empty( $category_ids ) ) {
			wp_set_post_terms( $post_id, $category_ids, 'category', false );
		}
	}

	/**
	 * Assign post tags.
	 *
	 * @param int   $post_id Post ID.
	 * @param mixed $tags    Tag list.
	 * @return void
	 */
	private function assign_tags( $post_id, $tags ) {
		$tag_names = array();
		$items     = is_array( $tags ) ? $tags : array( $tags );

		foreach ( $items as $item ) {
			$name = sanitize_text_field( (string) $item );
			if ( '' !== $name ) {
				$tag_names[] = $name;
			}
		}

		if ( ! empty( $tag_names ) ) {
			wp_set_post_terms( $post_id, $tag_names, 'post_tag', false );
		}
	}

	/**
	 * Apply configured category mapping for a GeoCraft category slug.
	 *
	 * Used when the publish payload omits explicit categories but provides
	 * a geocraft_category field.
	 *
	 * @param int    $post_id      Post ID.
	 * @param string $geo_category GeoCraft category name/slug.
	 * @return void
	 */
	private function apply_category_mapping( $post_id, $geo_category ) {
		$settings = new Geocraft_Settings();
		$mappings = $settings->get_taxonomy_mappings();
		$map      = $mappings['category_map'];

		if ( empty( $map[ $geo_category ] ) ) {
			return;
		}

		$entry      = $map[ $geo_category ];
		$wp_term_id = absint( $entry['wp_term_id'] ?? 0 );
		$auto_create = ! empty( $entry['auto_create'] );

		if ( $wp_term_id > 0 ) {
			wp_set_post_terms( $post_id, array( $wp_term_id ), 'category', false );
			return;
		}

		if ( $auto_create ) {
			$term = term_exists( $geo_category, 'category' );
			if ( ! $term ) {
				$term = wp_insert_term( $geo_category, 'category' );
			}
			if ( ! is_wp_error( $term ) ) {
				$term_id = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
				if ( $term_id > 0 ) {
					wp_set_post_terms( $post_id, array( $term_id ), 'category', false );
				}
			}
		}
	}

	/**
	 * Apply configured default tags for a content type.
	 *
	 * Used when the publish payload omits explicit tags but provides
	 * a content_type field.
	 *
	 * @param int    $post_id      Post ID.
	 * @param string $content_type Content type key (e.g. 'blog_post').
	 * @return void
	 */
	private function apply_content_type_tags( $post_id, $content_type ) {
		$settings = new Geocraft_Settings();
		$mappings = $settings->get_taxonomy_mappings();
		$tag_map  = $mappings['content_type_tags'];

		if ( empty( $tag_map[ $content_type ] ) ) {
			return;
		}

		$tags = array_filter( array_map( 'trim', explode( ',', $tag_map[ $content_type ] ) ) );
		if ( ! empty( $tags ) ) {
			wp_set_post_terms( $post_id, array_values( $tags ), 'post_tag', false );
		}
	}

	/**
	 * Download and attach featured image from URL.
	 *
	 * @param int    $post_id     Post ID.
	 * @param string $image_url   Image URL.
	 * @return int|WP_Error Attachment ID or WP_Error.
	 */
	private function attach_featured_image( $post_id, $image_url ) {
		$image_url = esc_url_raw( $image_url );
		if ( '' === $image_url ) {
			return new WP_Error(
				'geocraft_invalid_featured_image_url',
				__( 'featured_image_url is invalid.', 'geocraft-plugin' ),
				array( 'status' => 400 )
			);
		}

		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$temp_file = download_url( $image_url );
		if ( is_wp_error( $temp_file ) ) {
			return new WP_Error(
				'geocraft_featured_image_download_failed',
				sprintf(
					/* translators: %s: error message */
					__( 'Unable to download featured image: %s', 'geocraft-plugin' ),
					$temp_file->get_error_message()
				),
				array( 'status' => 400 )
			);
		}

		$file_array = array(
			'name'     => wp_basename( parse_url( $image_url, PHP_URL_PATH ) ?: 'featured-image' ),
			'tmp_name' => $temp_file,
		);

		$attachment_id = media_handle_sideload( $file_array, $post_id );
		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $temp_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return new WP_Error(
				'geocraft_featured_image_attach_failed',
				sprintf(
					/* translators: %s: error message */
					__( 'Unable to attach featured image: %s', 'geocraft-plugin' ),
					$attachment_id->get_error_message()
				),
				array( 'status' => 500 )
			);
		}

		set_post_thumbnail( $post_id, (int) $attachment_id );
		return (int) $attachment_id;
	}
}
