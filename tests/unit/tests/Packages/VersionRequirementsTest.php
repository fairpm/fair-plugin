<?php
/**
 * Tests for FAIR\Packages\version_requirements().
 *
 * @package FAIR
 */

use FAIR\Tests\Factory\ReleaseDocumentFactory;
use function FAIR\Packages\version_requirements;

/**
 * Tests for FAIR\Packages\version_requirements().
 *
 * @covers FAIR\Packages\version_requirements
 */
class VersionRequirementsTest extends WP_UnitTestCase {

	/**
	 * Test should extract requires_php from env:php.
	 */
	public function test_should_extract_requires_php() {
		$release = ReleaseDocumentFactory::builder()
			->with_requires( [ 'env:php' => '>=8.0' ] )
			->build();

		$actual = version_requirements( $release );

		$this->assertArrayHasKey( 'requires_php', $actual, 'Should have requires_php key.' );
		$this->assertSame( '8.0', $actual['requires_php'], 'Should strip leading operator.' );
	}

	/**
	 * Test should extract requires_wp from env:wp in requires.
	 */
	public function test_should_extract_requires_wp() {
		$release = ReleaseDocumentFactory::builder()
			->with_requires( [ 'env:wp' => '>=5.4' ] )
			->build();

		$actual = version_requirements( $release );

		$this->assertArrayHasKey( 'requires_wp', $actual, 'Should have requires_wp key.' );
		$this->assertSame( '5.4', $actual['requires_wp'], 'Should strip leading operator.' );
	}

	/**
	 * Test should extract tested_to from env:wp in suggests.
	 */
	public function test_should_extract_tested_to() {
		$release = ReleaseDocumentFactory::builder()
			->with_suggests( [ 'env:wp' => '>=6.0' ] )
			->build();

		$actual = version_requirements( $release );

		$this->assertArrayHasKey( 'tested_to', $actual, 'Should have tested_to key.' );
		$this->assertSame( '6.0', $actual['tested_to'], 'Should strip leading operator.' );
	}

	/**
	 * Test should extract all three fields from a full release.
	 */
	public function test_should_extract_all_three_fields() {
		$release = ReleaseDocumentFactory::with_requirements();

		$actual = version_requirements( $release );

		$this->assertSame( '8.0', $actual['requires_php'] ?? '', 'Should extract requires_php.' );
		$this->assertSame( '5.4', $actual['requires_wp'] ?? '', 'Should extract requires_wp.' );
		$this->assertSame( '6.4', $actual['tested_to'] ?? '', 'Should extract tested_to.' );
	}

	/**
	 * Test should return empty array when no requires or suggests.
	 */
	public function test_should_return_empty_array_for_no_requirements() {
		$release = ReleaseDocumentFactory::builder()
			->set( 'requires', (object) [] )
			->set( 'suggests', (object) [] )
			->build();

		$actual = version_requirements( $release );

		$this->assertEmpty( $actual, 'No requirements should return empty array.' );
	}

	/**
	 * Test should strip version prefix operators like ^ and ~.
	 */
	public function test_should_strip_caret_and_tilde_prefixes() {
		$release = ReleaseDocumentFactory::builder()
			->with_requires( [ 'env:php' => '^8.1' ] )
			->with_suggests( [ 'env:wp' => '~6.2' ] )
			->build();

		$actual = version_requirements( $release );

		$this->assertSame( '8.1', $actual['requires_php'], 'Caret prefix should be stripped.' );
		$this->assertSame( '6.2', $actual['tested_to'], 'Tilde prefix should be stripped.' );
	}

	/**
	 * Test should ignore non-env requirements.
	 */
	public function test_should_ignore_non_env_packages() {
		$release = ReleaseDocumentFactory::builder()
			->with_requires( [
				'env:php' => '>=8.0',
				'env:wp'  => '>=5.4',
				'package:some-lib' => '^1.0',
			] )
			->build();

		$actual = version_requirements( $release );

		$this->assertArrayHasKey( 'requires_php', $actual, 'Should still extract requires_php.' );
		$this->assertArrayHasKey( 'requires_wp', $actual, 'Should still extract requires_wp.' );
		$this->assertArrayNotHasKey( 'package:some-lib', $actual, 'Non-env packages should be ignored.' );
	}

	/**
	 * Test should handle missing requires property.
	 */
	public function test_should_handle_missing_requires() {
		$release = ReleaseDocumentFactory::builder()
			->unset( 'requires' )
			->with_suggests( [ 'env:wp' => '>=6.0' ] )
			->build();

		$actual = version_requirements( $release );

		$this->assertArrayNotHasKey( 'requires_php', $actual, 'Missing requires should not set requires_php.' );
		$this->assertSame( '6.0', $actual['tested_to'], 'tested_to should still be extracted from suggests.' );
	}

	/**
	 * Test should handle missing suggests property.
	 */
	public function test_should_handle_missing_suggests() {
		$release = ReleaseDocumentFactory::builder()
			->with_requires( [ 'env:php' => '>=8.0' ] )
			->unset( 'suggests' )
			->build();

		$actual = version_requirements( $release );

		$this->assertSame( '8.0', $actual['requires_php'], 'requires_php should be extracted.' );
		$this->assertArrayNotHasKey( 'tested_to', $actual, 'Missing suggests should not set tested_to.' );
	}
}
