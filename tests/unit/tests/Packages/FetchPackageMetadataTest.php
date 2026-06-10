<?php
/**
 * Tests for FAIR\Packages\fetch_package_metadata().
 *
 * @package FAIR
 */

use function FAIR\Packages\fetch_package_metadata;
use function FAIR\Packages\cache_update_error;
use const FAIR\Packages\CACHE_METADATA_DOCUMENTS;
use const FAIR\Packages\CACHE_UPDATE_ERRORS;

/**
 * Tests for FAIR\Packages\fetch_package_metadata().
 *
 * @covers FAIR\Packages\fetch_package_metadata
 */
class FetchPackageMetadataTest extends WP_UnitTestCase {

	private string $test_did = 'did:plc:z72i7hdynmk6r22z27h6tvur';
	private string $cache_key;

	public function set_up() {
		parent::set_up();
		$this->cache_key = CACHE_METADATA_DOCUMENTS . $this->test_did;
	}

	/**
	 * Tear down: remove all transients and HTTP filters.
	 */
	public function tear_down() {
		delete_site_transient( $this->cache_key );
		delete_site_transient( CACHE_UPDATE_ERRORS . $this->test_did );
		delete_site_transient( 'fair_did_error_' . $this->test_did );
		remove_all_filters( 'pre_http_request' );
		parent::tear_down();
	}

	/**
	 * Seed a DID document with a service endpoint.
	 */
	private function seed_did_document_with_service(): void {
		set_site_transient( $this->cache_key, [
			'id'                 => $this->test_did,
			'alsoKnownAs'        => [],
			'verificationMethod' => $this->fake_keys(),
			'service'            => [
				[
					'type'            => 'FairPackageManagementRepo',
					'serviceEndpoint' => 'https://test.example.com/metadata/' . $this->test_did,
				],
			],
		], HOUR_IN_SECONDS );
	}

	/**
	 * Seed valid keys for signing.
	 */
	private function fake_keys(): array {
		return [
			[
				'id'                 => '#' . $this->test_did,
				'type'               => 'Multikey',
				'publicKeyMultibase'  => 'z6MkhaXgBXDvQtf5VgJKe',
			],
		];
	}

	/**
	 * Valid metadata body for HTTP mocking.
	 */
	private function valid_metadata_body(): string {
		return json_encode( (object) [
			'id'       => $this->test_did,
			'type'     => 'wp-plugin',
			'license'  => 'GPL-2.0-only',
			'authors'  => [ (object) [ 'name' => 'Test Author', 'url' => 'https://example.com' ] ],
			'security' => [],
			'releases' => [
				(object) [
					'version'   => '1.0.0',
					'artifacts' => (object) [ 'package' => [] ],
				],
			],
		] );
	}

	/**
	 * Test should return WP_Error when DID document has no service.
	 */
	public function test_should_return_error_when_no_service() {
		set_site_transient( $this->cache_key, [
			'id'                 => $this->test_did,
			'alsoKnownAs'        => [],
			'verificationMethod' => [],
			'service'            => [],
		], HOUR_IN_SECONDS );

		$actual = fetch_package_metadata( $this->test_did );

		$this->assertWPError( $actual, 'No service should return WP_Error.' );
		$this->assertSame(
			'fair.packages.fetch_metadata.no_service',
			$actual->get_error_code(),
			'Error code should indicate no service.'
		);
	}

	/**
	 * Test should return WP_Error when fetched metadata ID does not match requested DID.
	 */
	public function test_should_return_error_when_id_mismatch() {
		$this->seed_did_document_with_service();

		add_filter( 'pre_http_request', function () {
			return [
				'body'     => json_encode( (object) [
					'id'       => 'did:plc:other-wrong-id',
					'type'     => 'wp-plugin',
					'license'  => 'GPL-2.0-only',
					'authors'  => [ (object) [ 'name' => 'Test', 'url' => 'https://example.com' ] ],
					'security' => [],
					'releases' => [
						(object) [
							'version'   => '1.0.0',
							'artifacts' => (object) [ 'package' => [] ],
						],
					],
				] ),
				'response' => [ 'code' => 200 ],
				'headers'  => [],
			];
		}, 10, 0 );

		$actual = fetch_package_metadata( $this->test_did );

		$this->assertWPError( $actual, 'ID mismatch should return WP_Error.' );
		$this->assertSame(
			'fair.packages.fetch_metadata.mismatch',
			$actual->get_error_code(),
			'Error code should indicate ID mismatch.'
		);
	}

	/**
	 * Test should successfully return metadata for valid DID.
	 */
	public function test_should_return_metadata_on_success() {
		$this->seed_did_document_with_service();

		add_filter( 'pre_http_request', function () {
			return [
				'body'     => $this->valid_metadata_body(),
				'response' => [ 'code' => 200 ],
				'headers'  => [],
			];
		}, 10, 0 );

		$actual = fetch_package_metadata( $this->test_did );

		$this->assertInstanceOf(
			\FAIR\Packages\MetadataDocument::class,
			$actual,
			'Should return MetadataDocument on success.'
		);
		$this->assertSame( $this->test_did, $actual->id, 'Returned metadata should have matching ID.' );
	}

	/**
	 * Test should propagate DID document fetch error.
	 */
	public function test_should_return_cached_error_from_did_fetch() {
		$cached_error = new WP_Error( 'fair.packages.did.fetch_error', 'Cached DID error' );
		set_site_transient( CACHE_UPDATE_ERRORS . $this->test_did, $cached_error, HOUR_IN_SECONDS );

		$actual = fetch_package_metadata( $this->test_did );

		$this->assertWPError( $actual, 'Should return cached error from DID fetch.' );
	}
}

/**
 * Tests for FAIR\Packages\get_latest_release_from_did().
 *
 * @covers FAIR\Packages\get_latest_release_from_did
 */
