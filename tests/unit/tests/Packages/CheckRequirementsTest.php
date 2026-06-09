<?php
/**
 * Tests for FAIR\Packages\check_requirements().
 *
 * @package FAIR
 */

use FAIR\Tests\Factory\ReleaseDocumentFactory;
use function FAIR\Packages\check_requirements;

/**
 * Tests for FAIR\Packages\check_requirements().
 *
 * @covers FAIR\Packages\check_requirements
 */
class CheckRequirementsTest extends WP_UnitTestCase {

	/**
	 * Test should return true when all requirements are met.
	 */
	public function test_should_return_true_when_all_requirements_met() {
		$release = ReleaseDocumentFactory::builder()
			->with_requires( [ 'env:php' => '>=8.0', 'env:wp' => '>=5.4' ] )
			->build();

		$actual = check_requirements( $release );

		$this->assertTrue( $actual, 'Requirements that are met should return true.' );
	}

	/**
	 * Test should return false when a PHP requirement is unmet.
	 */
	public function test_should_return_false_when_php_unmet() {
		$release = ReleaseDocumentFactory::builder()
			->with_requires( [ 'env:php' => '>=99.0.0' ] )
			->build();

		$actual = check_requirements( $release );

		$this->assertFalse( $actual, 'Unmet PHP requirement should return false.' );
	}

	/**
	 * Test should return false when a WP requirement is unmet.
	 */
	public function test_should_return_false_when_wp_unmet() {
		$release = ReleaseDocumentFactory::builder()
			->with_requires( [ 'env:wp' => '>=99.0.0' ] )
			->build();

		$actual = check_requirements( $release );

		$this->assertFalse( $actual, 'Unmet WP requirement should return false.' );
	}

	/**
	 * Test should return true when no requires are set.
	 */
	public function test_should_return_true_when_no_requires() {
		$release = ReleaseDocumentFactory::builder()
			->set( 'requires', (object) [] )
			->build();

		$actual = check_requirements( $release );

		$this->assertTrue( $actual, 'Empty requires should return true (nothing unmet).' );
	}
}
