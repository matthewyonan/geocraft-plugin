<?php
/**
 * GeoCraft Analytics collector.
 *
 * Tracks pageviews, time-on-page, and bounce rate for GeoCraft-published posts,
 * then batch-sends the data to the GeoCraft platform via WP-Cron.
 *
 * @package GeocraftPlugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Geocraft_Analytics
 */
class Geocraft_Analytics {

	/** Option key for the pending send buffer (autoload=false). */
	const BUFFER_OPTION = 'geocraft_analytics_buffer';

	/** Option key for cumulative per-post stats shown in the dashboard widget. */
	const STATS_OPTION = 'geocraft_analytics_stats';

	/** WP-Cron hook name. */
	const CRON_HOOK = 'geocraft_send_analytics';

	/** AJAX action for the JS time-on-page beacon. */
	const AJAX_BEACON = 'geocraft_analytics_beacon';

	/** Nonce action prefix (suffixed with wp post id). */
	const NONCE_PREFIX = 'geocraft_beacon_';

	// -------------------------------------------------------------------------
	// Bootstrap
	// -------------------------------------------------------------------------

	public function __construct() {
		// Front-end: track pageviews.
		add_action( 'wp', array( $this, 'maybe_track_pageview' ) );

		// Front-end: enqueue beacon script on GeoCraft posts.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );

		// AJAX beacon for time-on-page data (logged-in and logged-out users).
		add_action( 'wp_ajax_' . self::AJAX_BEACON, array( $this, 'handle_beacon' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_BEACON, array( $this, 'handle_beacon' ) );

		// Cron: flush buffered events to GeoCraft API.
		add_action( self::CRON_HOOK, array( $this, 'send_buffered_analytics' ) );

		// Admin dashboard widget.
		add_action( 'wp_dashboard_setup', array( $this, 'register_dashboard_widget' ) );
	}

	/**
	 * Schedule the hourly cron job on plugin activation.
	 *
	 * @return void
	 */
	public static function schedule_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'hourly', self::CRON_HOOK );
		}
	}

	/**
	 * Remove the cron job on plugin deactivation.
	 *
	 * @return void
	 */
	public static function unschedule_cron() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	// -------------------------------------------------------------------------
	// Front-end tracking
	// -------------------------------------------------------------------------

	/**
	 * Record a pageview when a single GeoCraft-published post is loaded.
	 *
	 * Hooked on 'wp' so is_singular() is reliable.
	 *
	 * @return void
	 */
	public function maybe_track_pageview() {
		if ( ! is_singular( 'post' ) ) {
			return;
		}

		$wp_post_id       = (int) get_the_ID();
		$geocraft_post_id = get_post_meta( $wp_post_id, Geocraft_Publisher::META_POST_ID, true );

		if ( empty( $geocraft_post_id ) ) {
			return;
		}

		$this->record_event(
			(string) $geocraft_post_id,
			$wp_post_id,
			'pageview'
		);
	}

	/**
	 * Enqueue the front-end analytics beacon script on GeoCraft posts.
	 *
	 * @return void
	 */
	public function enqueue_frontend_scripts() {
		if ( ! is_singular( 'post' ) ) {
			return;
		}

		$wp_post_id       = (int) get_the_ID();
		$geocraft_post_id = get_post_meta( $wp_post_id, Geocraft_Publisher::META_POST_ID, true );

		if ( empty( $geocraft_post_id ) ) {
			return;
		}

		wp_enqueue_script(
			'geocraft-analytics',
			plugin_dir_url( dirname( __FILE__ ) ) . 'admin/assets/js/geocraft-analytics.js',
			array(),
			defined( 'GEOCRAFT_VERSION' ) ? GEOCRAFT_VERSION : '0.1.0',
			true
		);

		wp_localize_script(
			'geocraft-analytics',
			'geocraftAnalytics',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'action'         => self::AJAX_BEACON,
				'nonce'          => wp_create_nonce( self::NONCE_PREFIX . $wp_post_id ),
				'geocraftPostId' => (string) $geocraft_post_id,
				'wpPostId'       => $wp_post_id,
			)
		);
	}

	// -------------------------------------------------------------------------
	// AJAX beacon
	// -------------------------------------------------------------------------

	/**
	 * Handle the JS time-on-page beacon request.
	 *
	 * Expected POST fields:
	 *   nonce           string  WP nonce (geocraft_beacon_{wp_post_id})
	 *   wp_post_id      int     WordPress post ID
	 *   geocraft_post_id string GeoCraft remote post ID
	 *   time_on_page    int     Seconds spent on page
	 *   is_bounce       bool    Whether the visit is a bounce (0 or 1)
	 *
	 * @return void
	 */
	public function handle_beacon() {
		$wp_post_id = isset( $_POST['wp_post_id'] ) ? absint( $_POST['wp_post_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$nonce      = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! wp_verify_nonce( $nonce, self::NONCE_PREFIX . $wp_post_id ) ) {
			wp_send_json_error( null, 403 );
		}

		$geocraft_post_id = isset( $_POST['geocraft_post_id'] ) ? sanitize_text_field( wp_unslash( $_POST['geocraft_post_id'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$time_on_page     = isset( $_POST['time_on_page'] ) ? absint( $_POST['time_on_page'] ) : 0; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$is_bounce        = ! empty( $_POST['is_bounce'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		if ( '' !== $geocraft_post_id && $time_on_page > 0 ) {
			$this->record_event(
				$geocraft_post_id,
				$wp_post_id,
				'time_on_page',
				array(
					'seconds'   => $time_on_page,
					'is_bounce' => $is_bounce,
				)
			);
		}

		wp_send_json_success();
	}

	// -------------------------------------------------------------------------
	// Buffer / cron
	// -------------------------------------------------------------------------

	/**
	 * Flush buffered analytics events to the GeoCraft platform API.
	 *
	 * Called by WP-Cron (self::CRON_HOOK). On failure the events are
	 * prepended back to the buffer so they are retried on the next run.
	 *
	 * @return void
	 */
	public function send_buffered_analytics() {
		$buffer = get_option( self::BUFFER_OPTION, array() );
		if ( empty( $buffer ) ) {
			return;
		}

		// Clear buffer optimistically before the request to avoid double-sends
		// when multiple cron invocations overlap.
		update_option( self::BUFFER_OPTION, array(), false );

		$api    = new Geocraft_API();
		$result = $api->push_analytics(
			array(
				'site'   => get_bloginfo( 'url' ),
				'events' => $buffer,
			)
		);

		if ( is_wp_error( $result ) ) {
			// Re-queue the unsent events at the front of the buffer.
			$remaining = get_option( self::BUFFER_OPTION, array() );
			update_option( self::BUFFER_OPTION, array_merge( $buffer, $remaining ), false );
		}
	}

	// -------------------------------------------------------------------------
	// Admin dashboard widget
	// -------------------------------------------------------------------------

	/**
	 * Register the GeoCraft Analytics dashboard widget (admins only).
	 *
	 * @return void
	 */
	public function register_dashboard_widget() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'geocraft_analytics_widget',
			__( 'GeoCraft Analytics', 'geocraft-plugin' ),
			array( $this, 'render_dashboard_widget' )
		);
	}

	/**
	 * Render the dashboard widget content.
	 *
	 * Shows per-post cumulative stats (pageviews, average time-on-page, bounces)
	 * aggregated from all events stored locally. Events are preserved until they
	 * have been successfully sent to the platform.
	 *
	 * @return void
	 */
	public function render_dashboard_widget() {
		$stats = get_option( self::STATS_OPTION, array() );

		if ( empty( $stats ) ) {
			echo '<p>' . esc_html__( 'No analytics data collected yet.', 'geocraft-plugin' ) . '</p>';
		} else {
			echo '<table class="widefat striped geocraft-analytics-table">';
			echo '<thead><tr>';
			echo '<th>' . esc_html__( 'Post', 'geocraft-plugin' ) . '</th>';
			echo '<th style="text-align:right">' . esc_html__( 'Pageviews', 'geocraft-plugin' ) . '</th>';
			echo '<th style="text-align:right">' . esc_html__( 'Avg. Time (s)', 'geocraft-plugin' ) . '</th>';
			echo '<th style="text-align:right">' . esc_html__( 'Bounces', 'geocraft-plugin' ) . '</th>';
			echo '</tr></thead><tbody>';

			foreach ( $stats as $wp_post_id => $stat ) {
				$pageviews     = absint( $stat['pageviews'] );
				$total_time    = absint( $stat['total_time'] );
				$bounces       = absint( $stat['bounces'] );
				$time_sessions = absint( $stat['time_sessions'] );
				$avg_time      = $time_sessions > 0 ? (int) round( $total_time / $time_sessions ) : 0;

				$title     = get_the_title( (int) $wp_post_id );
				$permalink = get_permalink( (int) $wp_post_id );

				echo '<tr>';
				echo '<td><a href="' . esc_url( (string) $permalink ) . '">' . esc_html( $title ) . '</a></td>';
				echo '<td style="text-align:right">' . absint( $pageviews ) . '</td>';
				echo '<td style="text-align:right">' . absint( $avg_time ) . '</td>';
				echo '<td style="text-align:right">' . absint( $bounces ) . '</td>';
				echo '</tr>';
			}

			echo '</tbody></table>';
		}

		$next_send = wp_next_scheduled( self::CRON_HOOK );
		if ( $next_send ) {
			echo '<p class="description" style="margin-top:8px">' .
				sprintf(
					/* translators: %s: human-readable time until next sync */
					esc_html__( 'Next sync to GeoCraft: %s', 'geocraft-plugin' ),
					esc_html( human_time_diff( time(), $next_send ) )
				) .
				'</p>';
		}

		$pending = count( (array) get_option( self::BUFFER_OPTION, array() ) );
		if ( $pending > 0 ) {
			echo '<p class="description">' .
				sprintf(
					/* translators: %d: number of pending events */
					esc_html__( '%d event(s) pending sync.', 'geocraft-plugin' ),
					$pending
				) .
				'</p>';
		}
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Append an analytics event to the send buffer and update local stats.
	 *
	 * @param string               $geocraft_post_id GeoCraft remote post ID.
	 * @param int                  $wp_post_id       Local WordPress post ID.
	 * @param string               $event_type       'pageview' or 'time_on_page'.
	 * @param array<string, mixed> $extra            Extra event fields.
	 * @return void
	 */
	private function record_event( $geocraft_post_id, $wp_post_id, $event_type, array $extra = array() ) {
		// --- send buffer ---------------------------------------------------
		$buffer = get_option( self::BUFFER_OPTION, array() );

		$buffer[] = array_merge(
			array(
				'geocraft_post_id' => $geocraft_post_id,
				'wp_post_id'       => $wp_post_id,
				'event_type'       => $event_type,
				'timestamp'        => time(),
			),
			$extra
		);

		update_option( self::BUFFER_OPTION, $buffer, false );

		// --- cumulative stats (for dashboard widget) -----------------------
		$stats = get_option( self::STATS_OPTION, array() );

		if ( ! isset( $stats[ $wp_post_id ] ) ) {
			$stats[ $wp_post_id ] = array(
				'pageviews'     => 0,
				'total_time'    => 0,
				'time_sessions' => 0,
				'bounces'       => 0,
			);
		}

		if ( 'pageview' === $event_type ) {
			++$stats[ $wp_post_id ]['pageviews'];
		} elseif ( 'time_on_page' === $event_type ) {
			$stats[ $wp_post_id ]['total_time'] += isset( $extra['seconds'] ) ? (int) $extra['seconds'] : 0;
			++$stats[ $wp_post_id ]['time_sessions'];
			if ( ! empty( $extra['is_bounce'] ) ) {
				++$stats[ $wp_post_id ]['bounces'];
			}
		}

		update_option( self::STATS_OPTION, $stats, false );
	}
}
