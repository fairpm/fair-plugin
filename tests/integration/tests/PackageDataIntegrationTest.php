<?php
/**
 * Integration tests: package data pipeline and error handling.
 *
 * Validates the end-to-end data assembly pipeline and various
 * error conditions against the mock DID/repo server.
 *
 * @package FAIR
 */

use PHPUnit\Framework\TestCase;

/**
 * @group integration
 */
class PackageDataIntegrationTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		wp_remote_post( 'http://mock-server:8080/log/reset', [ 'timeout' => 3 ] );
	}

	/**
	 * Test the full get_package_data pipeline returns a complete response.
	 */
	public function test_get_package_data_returns_complete_response(): void {
		$did  = 'did:plc:z72i7hdynmk6r22z27h6tvur';
		$data = \FAIR\Packages\get_package_data( $did );

		$this->assertIsArray( $data, 'get_package_data should return an array.' );

		// Verify the expected keys are present.
		$required_keys = [
			'name', 'slug', 'version', 'new_version',
			'package', 'download_link', 'requires', 'requires_php',
			'tested', 'sections', 'icons', 'banners',
			'last_updated', 'author', 'author_uri',
		];

		foreach ( $required_keys as $key ) {
			$this->assertArrayHasKey( $key, $data, "Response should have '{$key}' key." );
		}

		// Verify the version matches the latest release in the fixture (2.0.0).
		$this->assertSame( '2.0.0', $data['version'], 'Version should be latest release.' );
		$this->assertSame( '2.0.0', $data['new_version'], 'new_version should match.' );
	}

	/**
	 * Test that the response contains the FAIR metadata reference.
	 */
	public function test_get_package_data_includes_fair_metadata(): void {
		$did  = 'did:plc:z72i7hdynmk6r22z27h6tvur';
		$data = \FAIR\Packages\get_package_data( $did );

		$this->assertArrayHasKey( '_fair', $data, 'Response should have _fair metadata key.' );
		$this->assertInstanceOf(
			\FAIR\Packages\MetadataDocument::class,
			$data['_fair'],
			'_fair should be a MetadataDocument in raw get_package_data.'
		);
	}

	/**
	 * Test that a DID without a FairPackageManagementRepo service returns an error.
	 */
	public function test_get_package_data_fails_without_service(): void {
		// The 'did:plc:no-services' fixture has no FairPackageManagementRepo service.
		$result = \FAIR\Packages\get_package_data( 'did:plc:no-services' );

		$this->assertInstanceOf( WP_Error::class, $result, 'Should return WP_Error without service.' );
	}

	/**
	 * Test that an unknown DID returns an error.
	 */
	public function test_get_package_data_fails_for_unknown_did(): void {
		$result = \FAIR\Packages\get_package_data( 'did:plc:unknown1234567890123456' );

		$this->assertInstanceOf( WP_Error::class, $result, 'Unknown DID should return WP_Error.' );
	}

	/**
	 * Test that the DID document cache is used on subsequent requests.
	 */
	public function test_did_document_is_cached(): void {
		$did = 'did:plc:z72i7hdynmk6r22z27h6tvur';

		// First call — fetches from mock server.
		$first = \FAIR\Packages\get_did_document( $did );
		$this->assertIsArray( $first );

		// Second call — should use cache (no additional HTTP request).
		$second = \FAIR\Packages\get_did_document( $did );
		$this->assertSame( $first, $second, 'Cached result should be identical.' );
	}
}
