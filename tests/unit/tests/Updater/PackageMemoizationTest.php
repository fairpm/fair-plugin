<?php
/**
 * Tests for FAIR\Updater\Package::get_release() and get_metadata() memoization.
 *
 * Verifies caching behavior: successful fetches are cached and not
 * re-fetched; failed fetches (WP_Error) are NOT cached and retry on
 * subsequent calls.
 *
 * @package FAIR
 */

declare( strict_types=1 );

namespace FAIR\Tests\Updater;

use FAIR\Packages\ReleaseDocument;
use FAIR\Updater\PluginPackage;
use PHPUnit\Framework\TestCase;

/**
 * Tests memoization behavior of Package::get_release().
 *
 * Uses a pre_http_request filter to count fetch calls and return
 * controlled responses, verifying the caching semantics.
 *
 * @covers FAIR\Updater\PluginPackage::get_release
 */
class ReleaseMemoizationTest extends TestCase {

	private string $test_did = 'did:plc:z72i7hdynmk6r22z27h6tvur';
	private int $fetch_count = 0;

	protected function setUp(): void {
		parent::setUp();
		$this->fetch_count = 0;
		remove_all_filters( 'pre_http_request' );
	}

	protected function tearDown(): void {
		$this->cleanup_dir( WP_PLUGIN_DIR . '/memo-test-plugin' );
		// Clear all caches used by the release pipeline.
		delete_site_transient( \FAIR\Packages\CACHE_METADATA_DOCUMENTS . $this->test_did );
		delete_site_transient( 'fair-metadata-endpoint-' . $this->test_did );
		delete_site_transient( \FAIR\Packages\CACHE_KEY . md5(
			'https://test.example.com/metadata/' . $this->test_did
		) );
		delete_site_transient( \FAIR\Packages\CACHE_RELEASE_PACKAGES );
		delete_site_transient( \FAIR\Packages\CACHE_UPDATE_ERRORS . $this->test_did );
		remove_all_filters( 'pre_http_request' );
		parent::tearDown();
	}

	/**
	 * Create a plugin package for testing.
	 */
	private function make_package(): PluginPackage {
		$dir   = WP_PLUGIN_DIR . '/memo-test-plugin';
		$file  = $dir . '/memo-test-plugin.php';
		wp_mkdir_p( $dir );
		file_put_contents( $file, "<?php\n/**\n * Plugin Name: Memo Test\n * Version: 1.0.0\n */" );

		return new PluginPackage( $this->test_did, $file );
	}

	/**
	 * Helper: seed DID document + mock HTTP for metadata/release fetch.
	 *
	 * @param array|null $metadata_body  Metadata document body (null = HTTP error).
	 * @param array|null $release_body   Release document body (null = skip, use metadata).
	 */
	private function mock_pipeline( $metadata_body = null, $release_body = null ): void {
		// Seed DID document in cache so get_did_document() returns immediately.
		set_site_transient( \FAIR\Packages\CACHE_METADATA_DOCUMENTS . $this->test_did, [
			'id'                 => $this->test_did,
			'alsoKnownAs'        => [],
			'verificationMethod' => [
				[
					'id'                 => '#fair-key',
					'type'               => 'Multikey',
					'publicKeyMultibase'  => 'z6MkhaXgBXDvQtf5VgJKe',
				],
			],
			'service'            => [
				[
					'type'            => 'FairPackageManagementRepo',
					'serviceEndpoint' => 'https://test.example.com/metadata/' . $this->test_did,
				],
			],
		], HOUR_IN_SECONDS );

		$test = $this;

		add_filter( 'pre_http_request', function ( $preempt, $args, $url ) use ( $test, $metadata_body, $release_body ) {
			$test->fetch_count++;

			// Metadata fetch — return the release info directly in metadata format
			// so that fetch_package_metadata → fetch_metadata_doc → from_response chained call succeeds.
			if ( $metadata_body === null ) {
				// Simulate HTTP error.
				return new \WP_Error( 'http_request_failed', 'Mocked HTTP failure.' );
			}

			return [
				'body'     => json_encode( $metadata_body ),
				'response' => [ 'code' => 200 ],
				'headers'  => [],
			];
		}, 10, 3 );
	}

	private function valid_metadata(): object {
		return (object) [
			'id'       => $this->test_did,
			'type'     => 'wp-plugin',
			'license'  => 'GPL-2.0-only',
			'authors'  => [ (object) [ 'name' => 'Test', 'url' => 'https://example.com' ] ],
			'security' => [],
			'releases' => [
				(object) [
					'version'   => '2.0.0',
					'artifacts' => (object) [
						'package' => [
							(object) [
								'url'          => 'https://example.com/release.zip',
								'content-type' => 'application/zip',
							],
						],
					],
				],
			],
		];
	}

	/**
	 * Test that the first call to get_release() triggers a fetch.
	 */
	public function test_first_call_fetches(): void {
		$this->mock_pipeline( $this->valid_metadata() );
		$package = $this->make_package();

		$release = $package->get_release();

		$this->assertInstanceOf( ReleaseDocument::class, $release );
		$this->assertSame( 1, $this->fetch_count, 'First call should trigger one fetch.' );
	}

	/**
	 * Test that the second call to get_release() returns cached value without re-fetching.
	 */
	public function test_second_call_uses_cache(): void {
		$this->mock_pipeline( $this->valid_metadata() );
		$package = $this->make_package();

		$first  = $package->get_release();
		$second = $package->get_release();

		$this->assertSame( $first, $second, 'Second call should return the same cached object.' );
		$this->assertSame( 1, $this->fetch_count, 'Second call should not trigger another fetch.' );
	}

	/**
	 * Test that a WP_Error from the first call is NOT cached at the Package level
	 * — the second call retries. (Upstream error caching in get_did_document() does
	 * block re-fetching, but that's a separate concern — get_release() itself is
	 * stateless for errors.)
	 */
	public function test_wp_error_is_not_cached(): void {
		$this->mock_pipeline( null ); // null = HTTP error.
		$package = $this->make_package();

		$first  = $package->get_release();

		$this->assertInstanceOf( \WP_Error::class, $first, 'First call should return WP_Error.' );
		$this->assertSame( 1, $this->fetch_count, 'Error fetch should have been attempted.' );

		// Clear the upstream error cache to allow retry.
		delete_site_transient( \FAIR\Packages\CACHE_UPDATE_ERRORS . $this->test_did );

		$second = $package->get_release();

		$this->assertInstanceOf( \WP_Error::class, $second, 'Second call should also return WP_Error after retry.' );
		$this->assertSame( 2, $this->fetch_count, 'Error should not be cached in Package — second call re-fetches.' );
	}

	private function cleanup_dir( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$iter = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iter as $f ) {
			$f->isDir() ? rmdir( $f->getPathname() ) : unlink( $f->getPathname() );
		}
		rmdir( $dir );
	}
}
