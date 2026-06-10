<?php
/**
 * Tests for FAIR\Packages\get_plugin_information().
 *
 * @package FAIR
 */

use function FAIR\Packages\get_plugin_information;

/**
 * Tests for FAIR\Packages\get_plugin_information().
 *
 * @covers FAIR\Packages\get_plugin_information
 */
class GetPluginInformationTest extends WP_UnitTestCase {

	/**
	 * Test should return original result when action is not plugin_information.
	 */
	public function test_should_return_result_for_non_plugin_info_action() {
		$result = 'original';

		$actual = get_plugin_information( $result, 'some_other_action', (object) [ 'slug' => 'test' ] );

		$this->assertSame( $result, $actual, 'Should return original for non-plugin_info action.' );
	}

	/**
	 * Test should return original result when slug is empty.
	 */
	public function test_should_return_result_for_empty_slug() {
		$result = 'original';

		$actual = get_plugin_information( $result, 'plugin_information', (object) [ 'slug' => '' ] );

		$this->assertSame( $result, $actual, 'Should return original for empty slug.' );
	}

	/**
	 * Test should return original result when slug is not a DID.
	 */
	public function test_should_return_result_for_non_did_slug() {
		$result = (object) [ 'name' => 'Regular Plugin' ];

		$actual = get_plugin_information( $result, 'plugin_information', (object) [ 'slug' => 'regular-plugin' ] );

		$this->assertSame( $result, $actual, 'Should return original for non-DID slug.' );
	}
}
