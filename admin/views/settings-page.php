<?php
/**
 * Admin settings page view.
 *
 * Variables available in this template (injected by Geocraft_Settings::render_page):
 *   $geocraft_settings   array  Merged settings array.
 *   $geocraft_authors    array  WP_User[] with publish_posts capability.
 *   $geocraft_categories array  WP_Term[] of all categories.
 *
 * @package GeocraftPlugin
 */

defined( 'ABSPATH' ) || exit;

$geocraft_has_token = ! empty( $geocraft_settings['api_token'] );
?>
<div class="wrap geocraft-settings">
	<h1><?php esc_html_e( 'GeoCraft Settings', 'geocraft' ); ?></h1>

	<?php if ( isset( $_GET['updated'] ) && '1' === $_GET['updated'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Settings saved.', 'geocraft' ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="geocraft_save_settings">
		<?php wp_nonce_field( Geocraft_Settings::NONCE_ACTION, Geocraft_Settings::NONCE_FIELD ); ?>

		<table class="form-table" role="presentation">

			<!-- API Base URL -->
			<tr>
				<th scope="row">
					<label for="geocraft-api-base-url"><?php esc_html_e( 'API Base URL', 'geocraft' ); ?></label>
				</th>
				<td>
					<input
						type="url"
						id="geocraft-api-base-url"
						name="geocraft_api_base_url"
						class="regular-text"
						value="<?php echo esc_attr( $geocraft_settings['api_base_url'] ); ?>"
						placeholder="https://api.geocraft.ai/v1"
					>
					<p class="description">
						<?php esc_html_e( 'Leave blank to use the default GeoCraft API endpoint.', 'geocraft' ); ?>
					</p>
				</td>
			</tr>

			<!-- API Token -->
			<tr>
				<th scope="row">
					<label for="geocraft-api-token"><?php esc_html_e( 'API Key', 'geocraft' ); ?></label>
				</th>
				<td>
					<input
						type="password"
						id="geocraft-api-token"
						name="geocraft_api_token"
						class="regular-text"
						value="<?php echo $geocraft_has_token ? '••••••••' : ''; ?>"
						autocomplete="new-password"
					>
					<?php if ( $geocraft_has_token ) : ?>
						<p class="description geocraft-key-stored">
							<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
							<?php esc_html_e( 'An API key is stored. Enter a new value to replace it.', 'geocraft' ); ?>
						</p>
					<?php else : ?>
						<p class="description">
							<?php esc_html_e( 'Enter the API key from your GeoCraft account dashboard.', 'geocraft' ); ?>
						</p>
					<?php endif; ?>

					<button
						type="button"
						id="geocraft-test-connection"
						class="button button-secondary geocraft-test-btn"
						<?php disabled( ! $geocraft_has_token ); ?>
					>
						<?php esc_html_e( 'Test Connection', 'geocraft' ); ?>
					</button>
					<span id="geocraft-test-result" class="geocraft-test-result" aria-live="polite"></span>
				</td>
			</tr>

			<!-- Default Post Status -->
			<tr>
				<th scope="row">
					<label for="geocraft-default-status"><?php esc_html_e( 'Default Post Status', 'geocraft' ); ?></label>
				</th>
				<td>
					<select id="geocraft-default-status" name="geocraft_default_status">
						<option value="draft" <?php selected( $geocraft_settings['default_status'], 'draft' ); ?>>
							<?php esc_html_e( 'Draft', 'geocraft' ); ?>
						</option>
						<option value="publish" <?php selected( $geocraft_settings['default_status'], 'publish' ); ?>>
							<?php esc_html_e( 'Published', 'geocraft' ); ?>
						</option>
					</select>
					<p class="description">
						<?php esc_html_e( 'Status applied when GeoCraft publishes a new post to this site.', 'geocraft' ); ?>
					</p>
				</td>
			</tr>

			<!-- Default Author -->
			<tr>
				<th scope="row">
					<label for="geocraft-default-author"><?php esc_html_e( 'Default Author', 'geocraft' ); ?></label>
				</th>
				<td>
					<select id="geocraft-default-author" name="geocraft_default_author">
						<option value="0"><?php esc_html_e( '— Select author —', 'geocraft' ); ?></option>
						<?php foreach ( $geocraft_authors as $geocraft_author ) : ?>
							<option value="<?php echo absint( $geocraft_author->ID ); ?>" <?php selected( (int) $geocraft_settings['default_author'], (int) $geocraft_author->ID ); ?>>
								<?php echo esc_html( $geocraft_author->display_name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php esc_html_e( 'WordPress user assigned as the author of posts published by GeoCraft.', 'geocraft' ); ?>
					</p>
				</td>
			</tr>

			<!-- Default Category -->
			<tr>
				<th scope="row">
					<label for="geocraft-default-category"><?php esc_html_e( 'Default Category', 'geocraft' ); ?></label>
				</th>
				<td>
					<select id="geocraft-default-category" name="geocraft_default_category">
						<option value="0"><?php esc_html_e( '— Select category —', 'geocraft' ); ?></option>
						<?php foreach ( $geocraft_categories as $geocraft_category ) : ?>
							<option value="<?php echo absint( $geocraft_category->term_id ); ?>" <?php selected( (int) $geocraft_settings['default_category'], (int) $geocraft_category->term_id ); ?>>
								<?php echo esc_html( $geocraft_category->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php esc_html_e( 'Category applied to posts published by GeoCraft when no specific category is provided.', 'geocraft' ); ?>
					</p>
				</td>
			</tr>

		</table>

		<h2 class="title"><?php esc_html_e( 'Taxonomy Mapping', 'geocraft' ); ?></h2>
		<p><?php esc_html_e( 'Map GeoCraft content categories to WordPress categories and set default tags per content type. Applied when a publish request does not include explicit categories or tags.', 'geocraft' ); ?></p>

		<h3><?php esc_html_e( 'Category Mappings', 'geocraft' ); ?></h3>
		<p>
			<button type="button" id="geocraft-load-categories" class="button button-secondary">
				<?php esc_html_e( 'Load GeoCraft Categories', 'geocraft' ); ?>
			</button>
			<span id="geocraft-load-cats-result" class="geocraft-test-result" aria-live="polite"></span>
		</p>

		<table id="geocraft-category-map-table" class="wp-list-table widefat fixed striped geocraft-mapping-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'GeoCraft Category', 'geocraft' ); ?></th>
					<th><?php esc_html_e( 'WordPress Category', 'geocraft' ); ?></th>
					<th><?php esc_html_e( 'Auto-Create if Missing', 'geocraft' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody id="geocraft-category-map-body">
				<?php foreach ( $geocraft_taxonomy_mappings['category_map'] as $geocraft_geo_cat => $geocraft_entry ) : ?>
					<tr class="geocraft-map-row">
						<td>
							<input
								type="text"
								name="geocraft_category_map[<?php echo esc_attr( $geocraft_geo_cat ); ?>][geocraft_cat]"
								value="<?php echo esc_attr( $geocraft_geo_cat ); ?>"
								class="regular-text"
							>
						</td>
						<td>
							<select name="geocraft_category_map[<?php echo esc_attr( $geocraft_geo_cat ); ?>][wp_term_id]">
								<option value="0"><?php esc_html_e( '— Select category —', 'geocraft' ); ?></option>
								<?php foreach ( $geocraft_categories as $geocraft_category ) : ?>
									<option value="<?php echo absint( $geocraft_category->term_id ); ?>" <?php selected( (int) ( $geocraft_entry['wp_term_id'] ?? 0 ), (int) $geocraft_category->term_id ); ?>>
										<?php echo esc_html( $geocraft_category->name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
						<td>
							<input
								type="checkbox"
								name="geocraft_category_map[<?php echo esc_attr( $geocraft_geo_cat ); ?>][auto_create]"
								value="1"
								<?php checked( ! empty( $geocraft_entry['auto_create'] ) ); ?>
							>
						</td>
						<td>
							<button type="button" class="button button-small geocraft-remove-row">
								<?php esc_html_e( 'Remove', 'geocraft' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<button type="button" id="geocraft-add-cat-row" class="button button-secondary geocraft-add-row" style="margin-top:8px;">
			<?php esc_html_e( 'Add Row', 'geocraft' ); ?>
		</button>

		<h3 style="margin-top:24px;"><?php esc_html_e( 'Default Tags by Content Type', 'geocraft' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'Comma-separated tags applied when a published post matches the given content type and no tags are provided in the payload.', 'geocraft' ); ?>
		</p>

		<table id="geocraft-content-type-tags-table" class="wp-list-table widefat fixed striped geocraft-mapping-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Content Type', 'geocraft' ); ?></th>
					<th><?php esc_html_e( 'Default Tags (comma-separated)', 'geocraft' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody id="geocraft-content-type-body">
				<?php foreach ( $geocraft_taxonomy_mappings['content_type_tags'] as $geocraft_type => $geocraft_tags_str ) : ?>
					<tr class="geocraft-tag-row">
						<td>
							<input
								type="text"
								name="geocraft_content_type[<?php echo esc_attr( $geocraft_type ); ?>][type]"
								value="<?php echo esc_attr( $geocraft_type ); ?>"
								class="regular-text"
								placeholder="<?php esc_attr_e( 'e.g. blog_post', 'geocraft' ); ?>"
							>
						</td>
						<td>
							<input
								type="text"
								name="geocraft_content_type[<?php echo esc_attr( $geocraft_type ); ?>][tags]"
								value="<?php echo esc_attr( $geocraft_tags_str ); ?>"
								class="large-text"
								placeholder="<?php esc_attr_e( 'e.g. news, featured', 'geocraft' ); ?>"
							>
						</td>
						<td>
							<button type="button" class="button button-small geocraft-remove-row">
								<?php esc_html_e( 'Remove', 'geocraft' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<button type="button" id="geocraft-add-tag-row" class="button button-secondary geocraft-add-row" style="margin-top:8px;">
			<?php esc_html_e( 'Add Row', 'geocraft' ); ?>
		</button>

		<?php submit_button( __( 'Save Settings', 'geocraft' ) ); ?>
	</form>
</div>
