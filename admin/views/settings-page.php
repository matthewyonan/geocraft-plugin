<?php
/**
 * Admin settings page view.
 *
 * Variables available in this template (injected by Geocraft_Settings::render_page):
 *   $settings   array  Merged settings array.
 *   $authors    array  WP_User[] with publish_posts capability.
 *   $categories array  WP_Term[] of all categories.
 *
 * @package GeocraftPlugin
 */

defined( 'ABSPATH' ) || exit;

$has_token = ! empty( $settings['api_token'] );
?>
<div class="wrap geocraft-settings">
	<h1><?php esc_html_e( 'GeoCraft Settings', 'geocraft-plugin' ); ?></h1>

	<?php if ( isset( $_GET['updated'] ) && '1' === $_GET['updated'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Settings saved.', 'geocraft-plugin' ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="geocraft_save_settings">
		<?php wp_nonce_field( Geocraft_Settings::NONCE_ACTION, Geocraft_Settings::NONCE_FIELD ); ?>

		<table class="form-table" role="presentation">

			<!-- API Base URL -->
			<tr>
				<th scope="row">
					<label for="geocraft-api-base-url"><?php esc_html_e( 'API Base URL', 'geocraft-plugin' ); ?></label>
				</th>
				<td>
					<input
						type="url"
						id="geocraft-api-base-url"
						name="geocraft_api_base_url"
						class="regular-text"
						value="<?php echo esc_attr( $settings['api_base_url'] ); ?>"
						placeholder="https://api.geocraft.ai/v1"
					>
					<p class="description">
						<?php esc_html_e( 'Leave blank to use the default GeoCraft API endpoint.', 'geocraft-plugin' ); ?>
					</p>
				</td>
			</tr>

			<!-- API Token -->
			<tr>
				<th scope="row">
					<label for="geocraft-api-token"><?php esc_html_e( 'API Key', 'geocraft-plugin' ); ?></label>
				</th>
				<td>
					<input
						type="password"
						id="geocraft-api-token"
						name="geocraft_api_token"
						class="regular-text"
						value="<?php echo $has_token ? '••••••••' : ''; ?>"
						autocomplete="new-password"
					>
					<?php if ( $has_token ) : ?>
						<p class="description geocraft-key-stored">
							<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
							<?php esc_html_e( 'An API key is stored. Enter a new value to replace it.', 'geocraft-plugin' ); ?>
						</p>
					<?php else : ?>
						<p class="description">
							<?php esc_html_e( 'Enter the API key from your GeoCraft account dashboard.', 'geocraft-plugin' ); ?>
						</p>
					<?php endif; ?>

					<button
						type="button"
						id="geocraft-test-connection"
						class="button button-secondary geocraft-test-btn"
						<?php disabled( ! $has_token ); ?>
					>
						<?php esc_html_e( 'Test Connection', 'geocraft-plugin' ); ?>
					</button>
					<span id="geocraft-test-result" class="geocraft-test-result" aria-live="polite"></span>
				</td>
			</tr>

			<!-- Default Post Status -->
			<tr>
				<th scope="row">
					<label for="geocraft-default-status"><?php esc_html_e( 'Default Post Status', 'geocraft-plugin' ); ?></label>
				</th>
				<td>
					<select id="geocraft-default-status" name="geocraft_default_status">
						<option value="draft" <?php selected( $settings['default_status'], 'draft' ); ?>>
							<?php esc_html_e( 'Draft', 'geocraft-plugin' ); ?>
						</option>
						<option value="publish" <?php selected( $settings['default_status'], 'publish' ); ?>>
							<?php esc_html_e( 'Published', 'geocraft-plugin' ); ?>
						</option>
					</select>
					<p class="description">
						<?php esc_html_e( 'Status applied when GeoCraft publishes a new post to this site.', 'geocraft-plugin' ); ?>
					</p>
				</td>
			</tr>

			<!-- Default Author -->
			<tr>
				<th scope="row">
					<label for="geocraft-default-author"><?php esc_html_e( 'Default Author', 'geocraft-plugin' ); ?></label>
				</th>
				<td>
					<select id="geocraft-default-author" name="geocraft_default_author">
						<option value="0"><?php esc_html_e( '— Select author —', 'geocraft-plugin' ); ?></option>
						<?php foreach ( $authors as $author ) : ?>
							<option value="<?php echo absint( $author->ID ); ?>" <?php selected( (int) $settings['default_author'], (int) $author->ID ); ?>>
								<?php echo esc_html( $author->display_name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php esc_html_e( 'WordPress user assigned as the author of posts published by GeoCraft.', 'geocraft-plugin' ); ?>
					</p>
				</td>
			</tr>

			<!-- Default Category -->
			<tr>
				<th scope="row">
					<label for="geocraft-default-category"><?php esc_html_e( 'Default Category', 'geocraft-plugin' ); ?></label>
				</th>
				<td>
					<select id="geocraft-default-category" name="geocraft_default_category">
						<option value="0"><?php esc_html_e( '— Select category —', 'geocraft-plugin' ); ?></option>
						<?php foreach ( $categories as $category ) : ?>
							<option value="<?php echo absint( $category->term_id ); ?>" <?php selected( (int) $settings['default_category'], (int) $category->term_id ); ?>>
								<?php echo esc_html( $category->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php esc_html_e( 'Category applied to posts published by GeoCraft when no specific category is provided.', 'geocraft-plugin' ); ?>
					</p>
				</td>
			</tr>

		</table>

		<?php submit_button( __( 'Save Settings', 'geocraft-plugin' ) ); ?>
	</form>
</div>
