<?php
/**
 * Tests for FAIR\Packages\add_package_to_release_cache().
 *
 * @package FAIR
 */

use function FAIR\Packages\add_package_to_release_cache;
use const FAIR\Packages\CACHE_RELEASE_PACKAGES;
use const FAIR\Packages\CACHE_METADATA_DOCUMENTS;
use const FAIR\Packages\CACHE_UPDATE_ERRORS;

/**
 * Tests for FAIR\Packages\add_package_to_release_cache().
 *
 * @covers FAIR\Packages\add_package_to_release_cache
 */
class AddPackageToReleaseCacheTest extends WP_UnitTestCase {

	private string $test_did = 'did:plc:z72i7hdynmk6r22z27h6tvur';

	public function tear_down() {
		delete_site_transient( CACHE_RELEASE_PACKAGES );
		delete_site_transient( CACHE_METADATA_DOCUMENTS . $this->test_did );
		delete_site_transient( CACHE_UPDATE_ERRORS . $this->test_did );
		delete_site_transient( 'fair_did_error_' . $this->test_did );
		remove_all_filters( 'pre_http_request' );
		parent::tear_down();
	}

	/**
	 * Test should do nothing for empty DID.
	 */
	public function test_should_do_nothing_for_empty_did() {
		set_site_transient( CACHE_RELEASE_PACKAGES, [ 'did:plc:existing' => 'placeholder' ] );

		add_package_to_release_cache( '' );

		$releases = get_site_transient( CACHE_RELEASE_PACKAGES );
		$this->assertCount( 1, $releases, 'Empty DID should not modify cache.' );
	}

	/**
	 * Test should do nothing when get_latest_release returns error.
	 */
	public function test_should_do_nothing_when_release_fails() {
		set_site_transient( CACHE_RELEASE_PACKAGES, [ 'did:plc:existing' => 'placeholder' ] );

		// Error cached for DID: no document, no service → error.
		$error = new WP_Error( 'fair.packages.did.fetch_error', 'Failed' );
		set_site_transient( CACHE_UPDATE_ERRORS . $this->test_did, $error, HOUR_IN_SECONDS );

		add_package_to_release_cache( $this->test_did );

		$releases = get_site_transient( CACHE_RELEASE_PACKAGES );
		$this->assertArrayNotHasKey( $this->test_did, $releases, 'Failed release should not be cached.' );
	}
}

/**
 * Tests for FAIR\Packages\maybe_add_accept_header().
 *
 * @covers FAIR\Packages\maybe_add_accept_header
 */
