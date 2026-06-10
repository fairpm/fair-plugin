<?php
/**
 * Tests for FAIR\Pings\register_query_vars().
 *
 * @package FAIR
 */

use function FAIR\Pings\register_query_vars;

/**
 * Tests for FAIR\Pings\register_query_vars().
 *
 * @covers FAIR\Pings\register_query_vars
 */
class RegisterQueryVarsTest extends WP_UnitTestCase {

	/**
	 * Test should add fair_indexnow_key to query vars.
	 */
	public function test_should_add_indexnow_key_var() {
		$vars = [ 'existing_var' ];

		$actual = register_query_vars( $vars );

		$this->assertContains( 'fair_indexnow_key', $actual, 'Query var should be registered.' );
		$this->assertContains( 'existing_var', $actual, 'Existing vars should be preserved.' );
	}

	/**
	 * Test should work with empty vars array.
	 */
	public function test_should_work_with_empty_vars() {
		$actual = register_query_vars( [] );

		$this->assertSame( [ 'fair_indexnow_key' ], $actual, 'Should return only the key var.' );
	}
}
