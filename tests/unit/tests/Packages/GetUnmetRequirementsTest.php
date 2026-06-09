<?php
/**
 * Tests for FAIR\Packages\get_unmet_requirements().
 *
 * @package FAIR
 */

use function FAIR\Packages\get_unmet_requirements;

/**
 * Tests for FAIR\Packages\get_unmet_requirements().
 *
 * @covers FAIR\Packages\get_unmet_requirements
 */
class GetUnmetRequirementsTest extends WP_UnitTestCase {

	/**
	 * Test should return empty array when all requirements are met.
	 */
	public function test_should_return_empty_when_all_requirements_met() {
		// PHP_VERSION and wp_get_wp_version() should always be >= 8.0 and 5.4 in test env.
		$actual = get_unmet_requirements( [
			'env:php' => '>=8.0',
			'env:wp'  => '>=5.4',
		] );

		$this->assertEmpty( $actual, 'All met requirements should return empty.' );
	}

	/**
	 * Test should flag unmet PHP version requirement.
	 */
	public function test_should_flag_unmet_php_version() {
		$actual = get_unmet_requirements( [
			'env:php' => '>=99.0.0',
		] );

		$this->assertArrayHasKey( 'env:php', $actual, 'Should flag unmet PHP requirement.' );
	}

	/**
	 * Test should flag unmet WP version requirement.
	 */
	public function test_should_flag_unmet_wp_version() {
		$actual = get_unmet_requirements( [
			'env:wp' => '>=99.0.0',
		] );

		$this->assertArrayHasKey( 'env:wp', $actual, 'Should flag unmet WP requirement.' );
	}

	/**
	 * Test should handle empty requirements.
	 */
	public function test_should_return_empty_for_no_requirements() {
		$actual = get_unmet_requirements( [] );

		$this->assertEmpty( $actual, 'Empty requirements should return empty.' );
	}

	/**
	 * Test should report each unmet requirement as a joined string.
	 */
	public function test_should_report_each_unmet_requirement() {
		$actual = get_unmet_requirements( [
			'env:php' => '>=99.0.0, >=100.0.0',
		] );

		$this->assertArrayHasKey( 'env:php', $actual, 'Should have env:php key.' );
		$this->assertStringContainsString( ',', $actual['env:php'], 'Multiple unmet should be comma-joined.' );
	}

	/**
	 * Test should skip invalid requirement specifiers (no comparator).
	 */
	public function test_should_skip_invalid_requirement_specifiers() {
		$actual = get_unmet_requirements( [
			'env:php' => 'no-comparator-just-version',
		] );

		// No comparator means $comp_spn === 0, so it should be skipped.
		$this->assertEmpty( $actual, 'Invalid specifiers should be skipped.' );
	}

	/**
	 * Test should handle unknown env packages gracefully (not crash).
	 */
	public function test_should_handle_unknown_env_packages() {
		$actual = get_unmet_requirements( [
			'env:php-gmp' => '>=1.0',
			'env:unknown' => '>=1.0',
		] );

		// These are NOT YET IMPLEMENTED (todo markers), so they should be empty.
		$this->assertEmpty( $actual, 'Unknown env packages should be skipped (not yet implemented).' );
	}

	/**
	 * Test should correctly compare version with all operators.
	 *
	 * @dataProvider data_operator_comparisons
	 */
	public function test_should_compare_using_operator( string $req, bool $expected_unmet ) {
		$actual = get_unmet_requirements( [
			'env:php' => $req,
		] );

		if ( $expected_unmet ) {
			$this->assertArrayHasKey( 'env:php', $actual, "Requirement '{$req}' should be unmet." );
		} else {
			$this->assertEmpty( $actual, "Requirement '{$req}' should be met." );
		}
	}

	/**
	 * Data provider for operator comparisons.
	 */
	public function data_operator_comparisons(): array {
		$future = '99.0.0';
		$past   = '1.0.0';

		return [
			'>= future should be unmet'  => [ ">={$future}", true ],
			'>= past should be met'     => [ ">={$past}", false ],
			'> future should be unmet'   => [ ">{$future}", true ],
			'> past should be met'      => [ ">{$past}", false ],
			'<= future should be met'    => [ "<={$future}", false ],
			'< future should be met'     => [ "<{$future}", false ],
			'!= current should be unmet if exact' => [ '!=' . PHP_VERSION, true ],
			'== future should be unmet'  => [ "=={$future}", true ],
		];
	}
}
