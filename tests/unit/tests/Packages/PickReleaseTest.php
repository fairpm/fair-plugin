<?php
/**
 * Tests for FAIR\Packages\pick_release().
 *
 * @package FAIR
 */

use FAIR\Tests\Factory\ReleaseDocumentFactory;
use function FAIR\Packages\pick_release;

/**
 * Tests for FAIR\Packages\pick_release().
 *
 * @covers FAIR\Packages\pick_release
 */
class PickReleaseTest extends WP_UnitTestCase {

	/**
	 * Test should return the latest release when no version is specified.
	 */
	public function test_should_return_latest_when_no_version_specified() {
		$releases = ReleaseDocumentFactory::list_of( '1.0.0', '3.0.0', '2.0.0' );

		$actual = pick_release( $releases, null );

		$this->assertNotNull( $actual, 'Should return a release.' );
		$this->assertSame( '3.0.0', $actual->version, 'Latest version should be returned.' );
	}

	/**
	 * Test should return a specific version when requested.
	 */
	public function test_should_return_specific_version_when_requested() {
		$releases = ReleaseDocumentFactory::list_of( '1.0.0', '3.0.0', '2.0.0' );

		$actual = pick_release( $releases, '2.0.0' );

		$this->assertNotNull( $actual, 'Should return a release.' );
		$this->assertSame( '2.0.0', $actual->version, 'Requested version should be returned.' );
	}

	/**
	 * Test should return null when requested version not found.
	 */
	public function test_should_return_null_when_version_not_found() {
		$releases = ReleaseDocumentFactory::list_of( '1.0.0', '2.0.0' );

		$actual = pick_release( $releases, '99.0.0' );

		$this->assertNull( $actual, 'Should return null for non-existent version.' );
	}

	/**
	 * Test should sort versions correctly including pre-release versions.
	 */
	public function test_should_sort_by_version_comparison() {
		$releases = ReleaseDocumentFactory::list_of( '1.0.0', '1.10.0', '1.2.0', '1.0.1' );

		$actual = pick_release( $releases, null );

		$this->assertNotNull( $actual, 'Should return a release.' );
		$this->assertSame( '1.10.0', $actual->version, 'Should correctly sort 1.10.0 > 1.2.0.' );
	}

	/**
	 * Test should return the only release in a single-release list.
	 */
	public function test_should_return_only_release_in_single_release_list() {
		$releases = ReleaseDocumentFactory::list_of( '1.0.0' );

		$actual = pick_release( $releases, null );

		$this->assertNotNull( $actual, 'Should return a release.' );
		$this->assertSame( '1.0.0', $actual->version, 'Only release should be returned.' );
	}

	/**
	 * Test should return null for an empty releases array.
	 */
	public function test_should_return_null_for_empty_releases() {
		$actual = pick_release( [], null );

		$this->assertNull( $actual, 'Empty releases should return null.' );
	}

	/**
	 * Test should handle null version by returning latest.
	 */
	public function test_should_treat_null_version_as_latest() {
		$releases = ReleaseDocumentFactory::list_of( '1.0.0', '5.0.0' );

		$actual = pick_release( $releases, null );

		$this->assertSame( '5.0.0', $actual->version, 'Null version should select latest.' );
	}
}
