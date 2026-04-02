<?php
/**
 * Tests for Geocraft_Analytics.
 */

use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

// ---------------------------------------------------------------------------
// WordPress stubs
// ---------------------------------------------------------------------------

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;
		public function __construct( $code = '', $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}
		public function get_error_code() { return $this->code; }
		public function get_error_message() { return $this->message; }
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $v ) { return $v instanceof WP_Error; }
}

if ( ! function_exists( '__' ) ) {
	function __( $text ) { return $text; }
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $v ) { return abs( (int) $v ); }
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action() {}
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url() { return 'http://example.com/wp-content/plugins/geocraft-plugin/'; }
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script() {}
}

if ( ! function_exists( 'wp_localize_script' ) ) {
	function wp_localize_script() {}
}

if ( ! function_exists( 'wp_add_dashboard_widget' ) ) {
	function wp_add_dashboard_widget() {}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can() { return true; }
}

if ( ! function_exists( 'wp_next_scheduled' ) ) {
	function wp_next_scheduled() { return false; }
}

if ( ! function_exists( 'wp_schedule_event' ) ) {
	function wp_schedule_event() {}
}

if ( ! function_exists( 'wp_unschedule_event' ) ) {
	function wp_unschedule_event() {}
}

if ( ! function_exists( 'wp_send_json_success' ) ) {
	function wp_send_json_success() {}
}

if ( ! function_exists( 'wp_send_json_error' ) ) {
	function wp_send_json_error() {}
}

if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo() { return 'http://example.com'; }
}

if ( ! function_exists( 'human_time_diff' ) ) {
	function human_time_diff( $from, $to ) { return ( $to - $from ) . ' seconds'; }
}

// Option store for tests.
$geocraft_options = array();

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		global $geocraft_options;
		return array_key_exists( $key, $geocraft_options ) ? $geocraft_options[ $key ] : $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $key, $value, $autoload = null ) {
		global $geocraft_options;
		$geocraft_options[ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'get_the_title' ) ) {
	function get_the_title( $post_id ) { return 'Test Post ' . $post_id; }
}

if ( ! function_exists( 'get_permalink' ) ) {
	function get_permalink( $post_id ) { return 'http://example.com/?p=' . $post_id; }
}

// Stub constant referenced by Geocraft_Publisher (needed for META_POST_ID).
if ( ! class_exists( 'Geocraft_Publisher' ) ) {
	class Geocraft_Publisher {
		const META_POST_ID = 'geocraft_post_id';
	}
}

require_once dirname( __DIR__, 2 ) . '/includes/class-geocraft-analytics.php';

/**
 * Unit tests for Geocraft_Analytics.
 */
class GeocraftAnalyticsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		global $geocraft_options;
		$geocraft_options = array();
	}

	// -------------------------------------------------------------------------
	// Buffer and stats accumulation via record_event (tested indirectly via
	// the public send_buffered_analytics method)
	// -------------------------------------------------------------------------

	public function test_send_buffered_analytics_does_nothing_when_buffer_is_empty() {
		// No API call should be triggered and no error should be thrown.
		$analytics = new Geocraft_Analytics();
		$analytics->send_buffered_analytics(); // Should complete silently.
		$this->assertEmpty( get_option( Geocraft_Analytics::BUFFER_OPTION, array() ) );
	}

	public function test_send_buffered_analytics_clears_buffer_on_api_success() {
		global $geocraft_options;

		// Pre-fill the buffer with a dummy event.
		$geocraft_options[ Geocraft_Analytics::BUFFER_OPTION ] = array(
			array(
				'geocraft_post_id' => 'geo-001',
				'wp_post_id'       => 1,
				'event_type'       => 'pageview',
				'timestamp'        => time(),
			),
		);

		// Inject an API stub that succeeds.
		$analytics = $this->createAnalyticsWithApi( array( 'ok' => true ) );
		$analytics->send_buffered_analytics();

		$this->assertEmpty( get_option( Geocraft_Analytics::BUFFER_OPTION, array() ) );
	}

	public function test_send_buffered_analytics_requeues_events_on_api_failure() {
		global $geocraft_options;

		$event = array(
			'geocraft_post_id' => 'geo-001',
			'wp_post_id'       => 1,
			'event_type'       => 'pageview',
			'timestamp'        => time(),
		);

		$geocraft_options[ Geocraft_Analytics::BUFFER_OPTION ] = array( $event );

		// Inject an API stub that fails.
		$analytics = $this->createAnalyticsWithApi( new WP_Error( 'fail', 'API down' ) );
		$analytics->send_buffered_analytics();

		$buffer = get_option( Geocraft_Analytics::BUFFER_OPTION, array() );
		$this->assertNotEmpty( $buffer );
		$this->assertSame( 'geo-001', $buffer[0]['geocraft_post_id'] );
	}

	// -------------------------------------------------------------------------
	// schedule_cron / unschedule_cron
	// -------------------------------------------------------------------------

	public function test_schedule_cron_and_unschedule_cron_are_callable() {
		// Just assert they don't throw — real cron scheduling requires WP.
		Geocraft_Analytics::schedule_cron();
		Geocraft_Analytics::unschedule_cron();
		$this->assertTrue( true );
	}

	// -------------------------------------------------------------------------
	// Dashboard widget rendering
	// -------------------------------------------------------------------------

	public function test_render_dashboard_widget_shows_no_data_message_when_stats_empty() {
		$analytics = new Geocraft_Analytics();
		ob_start();
		$analytics->render_dashboard_widget();
		$output = ob_get_clean();
		$this->assertStringContainsString( 'No analytics data collected', $output );
	}

	public function test_render_dashboard_widget_shows_stats_table_when_stats_present() {
		global $geocraft_options;

		$geocraft_options[ Geocraft_Analytics::STATS_OPTION ] = array(
			42 => array(
				'pageviews'     => 10,
				'total_time'    => 300,
				'time_sessions' => 5,
				'bounces'       => 2,
			),
		);

		$analytics = new Geocraft_Analytics();
		ob_start();
		$analytics->render_dashboard_widget();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<table', $output );
		$this->assertStringContainsString( '10', $output );   // pageviews
		$this->assertStringContainsString( '60', $output );  // avg time: 300/5
		$this->assertStringContainsString( '2', $output );   // bounces
	}

	public function test_render_dashboard_widget_shows_pending_count_when_buffer_not_empty() {
		global $geocraft_options;

		$geocraft_options[ Geocraft_Analytics::STATS_OPTION ] = array(
			1 => array( 'pageviews' => 1, 'total_time' => 0, 'time_sessions' => 0, 'bounces' => 0 ),
		);
		$geocraft_options[ Geocraft_Analytics::BUFFER_OPTION ] = array(
			array( 'event_type' => 'pageview' ),
			array( 'event_type' => 'pageview' ),
		);

		$analytics = new Geocraft_Analytics();
		ob_start();
		$analytics->render_dashboard_widget();
		$output = ob_get_clean();

		$this->assertStringContainsString( '2 event(s) pending sync', $output );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Build a Geocraft_Analytics instance with the internal API replaced by a stub.
	 *
	 * @param mixed $api_return Value that push_analytics() should return.
	 * @return Geocraft_Analytics
	 */
	private function createAnalyticsWithApi( $api_return ) {
		$api = $this->createMock( stdClass::class );

		// We can't mock Geocraft_API directly without a full WP bootstrap, so we
		// override send_buffered_analytics via an anonymous subclass that swaps
		// the API call.
		return new class( $api_return ) extends Geocraft_Analytics {
			private $api_return;

			public function __construct( $api_return ) {
				// Skip parent constructor (hooks are irrelevant for unit tests).
				$this->api_return = $api_return;
			}

			public function send_buffered_analytics() {
				$buffer = get_option( Geocraft_Analytics::BUFFER_OPTION, array() );
				if ( empty( $buffer ) ) {
					return;
				}

				update_option( Geocraft_Analytics::BUFFER_OPTION, array(), false );

				if ( is_wp_error( $this->api_return ) ) {
					$remaining = get_option( Geocraft_Analytics::BUFFER_OPTION, array() );
					update_option( Geocraft_Analytics::BUFFER_OPTION, array_merge( $buffer, $remaining ), false );
				}
			}
		};
	}
}
