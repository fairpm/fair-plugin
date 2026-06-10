<?php
/**
 * Integration test: full DID resolution pipeline via mock server.
 *
 * Validates that the plugin can resolve DIDs, fetch metadata, and
 * get latest releases — exercising the entire pipeline against the
 * mock DID/repo server inside the Docker network.
 *
 * Runs INSIDE a real WordPress instance. Extends plain TestCase.
 *
 * @package FAIR
 */

use PHPUnit\Framework\TestCase;

/**
 * @group integration
 */
class DidResolutionIntegrationTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		// Clear mock server request log before each test.
		wp_remote_post( 'http://mock-server:8080/log/reset', [ 'timeout' => 3 ] );
	}

	private function getMockLog(): array {
		$response = wp_remote_get( 'http://mock-server:8080/log', [ 'timeout' => 3 ] );
		if ( is_wp_error( $response ) ) {
			return [];
		}
		return json_decode( wp_remote_retrieve_body( $response ), true ) ?: [];
	}

	public function test_mock_server_is_healthy(): void {
		$response = wp_remote_get( 'http://mock-server:8080/health', [ 'timeout' => 3 ] );

		$this->assertNotInstanceOf( WP_Error::class, $response );
		$this->assertSame( 200, wp_remote_retrieve_response_code( $response ) );
	}

	public function test_full_resolution_pipeline(): void {
		$did = 'did:plc:z72i7hdynmk6r22z27h6tvur';

		// 1. Resolve DID document via mock PLC directory.
		$did_doc = \FAIR\Packages\get_did_document( $did );
		$this->assertIsArray( $did_doc );
		$this->assertArrayHasKey( 'id', $did_doc );
		$this->assertSame( $did, $did_doc['id'] );

		// 2. Fetch package metadata via mock FAIR repo.
		$metadata = \FAIR\Packages\fetch_package_metadata( $did );
		$this->assertInstanceOf( \FAIR\Packages\MetadataDocument::class, $metadata );
		$this->assertSame( $did, $metadata->id );
		$this->assertNotEmpty( $metadata->releases );

		// 3. Get latest release.
		$release = \FAIR\Packages\get_latest_release_from_did( $did );
		$this->assertInstanceOf( \FAIR\Packages\ReleaseDocument::class, $release );
		$this->assertNotEmpty( $release->version );
	}

	public function test_mock_server_logs_plc_requests(): void {
		$this->markTestSkipped( 'Log file permissions inside Docker container need investigation.' );
	}

	public function test_unknown_did_returns_error(): void {
		$result = \FAIR\Packages\get_did_document( 'did:plc:unknown1234567890123456' );
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	/**
	 * Integration test for search_by_did() success path.
	 *
	 * Moved from PipelineWPTest (unit) where it used seed_full_pipeline()
	 * to mock the entire HTTP layer. The integration layer tests against
	 * the real mock server, so a schema change won't silently break the test.
	 */
	public function test_search_by_did_returns_plugin_result(): void {
		$did = 'did:plc:z72i7hdynmk6r22z27h6tvur';

		$result = \FAIR\Packages\search_by_did(
			[ 'plugins' => [] ],
			'query_plugins',
			(object) [ 'search' => urlencode( $did ) ]
		);

		$this->assertIsObject( $result, 'Should return object.' );
		$this->assertObjectHasProperty( 'plugins', $result, 'Result should have plugins property.' );
		$this->assertCount( 1, $result->plugins, 'Should have one plugin.' );
		$this->assertObjectHasProperty( 'info', $result, 'Result should have info property.' );
	}

	/**
	 * Integration test for add_package_to_release_cache() success path.
	 *
	 * Moved from PipelineWPTest (unit). Verifies flush-append behavior
	 * against the real mock server.
	 */
	public function test_add_package_to_release_cache_populates_cache(): void {
		$did = 'did:plc:z72i7hdynmk6r22z27h6tvur';

		// Clear cache first.
		delete_site_transient( \FAIR\Packages\CACHE_RELEASE_PACKAGES );

		\FAIR\Packages\add_package_to_release_cache( $did );

		$releases = get_site_transient( \FAIR\Packages\CACHE_RELEASE_PACKAGES );
		$this->assertIsArray( $releases, 'Releases cache should be an array.' );
		$this->assertArrayHasKey( $did, $releases, 'Release should be cached for this DID.' );
		$this->assertInstanceOf(
			\FAIR\Packages\ReleaseDocument::class,
			$releases[ $did ],
			'Cached value should be a ReleaseDocument.'
		);
	}
}
