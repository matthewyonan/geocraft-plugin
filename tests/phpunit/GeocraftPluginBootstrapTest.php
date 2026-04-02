<?php
/**
 * Basic bootstrap tests.
 */

use PHPUnit\Framework\TestCase;

/**
 * Ensures test bootstrap is wired.
 */
class GeocraftPluginBootstrapTest extends TestCase {

	/**
	 * Asserts test environment bootstrapped.
	 *
	 * @return void
	 */
	public function test_bootstrap_constant_is_defined() {
		$this->assertTrue( defined( 'GEOCRAFT_PLUGIN_TESTS_DIR' ) );
	}
}
