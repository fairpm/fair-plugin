<?php
/**
 * Tests for FAIR\Packages\add_package_to_release_cache(), maybe_add_accept_header(),
 * search_by_did(), and get_plugin_information().
 *
 * @package FAIR
 */

use function FAIR\Packages\add_package_to_release_cache;
use function FAIR\Packages\maybe_add_accept_header;
use function FAIR\Packages\search_by_did;
use function FAIR\Packages\get_plugin_information;
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

	private function seed_pipeline(): void {
		set_site_transient( CACHE_METADATA_DOCUMENTS . $this->test_did, [
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

		add_filter( 'pre_http_request', function () {
			return [
				'body'     => json_encode( (object) [
					'id'       => $this->test_did,
					'type'     => 'wp-plugin',
					'license'  => 'GPL-2.0-only',
					'authors'  => [ (object) [ 'name' => 'Test', 'url' => 'https://example.com' ] ],
					'security' => [],
					'releases' => [
						(object) [
							'version'   => '3.0.0',
							'artifacts' => (object) [
								'package' => [
									(object) [
										'url'          => 'https://github.com/repo/release.zip',
										'content-type' => 'application/octet-stream',
									],
								],
							],
						],
					],
				] ),
				'response' => [ 'code' => 200 ],
				'headers'  => [],
			];
		}, 10, 0 );
	}

	/**
	 * Test should add release to cache on success.
	 */
	public function test_should_add_release_to_cache() {
		$this->seed_pipeline();

		add_package_to_release_cache( $this->test_did );

		$releases = get_site_transient( CACHE_RELEASE_PACKAGES );
		$this->assertIsArray( $releases, 'Releases cache should be an array.' );
		$this->assertArrayHasKey( $this->test_did, $releases, 'Release should be cached for this DID.' );
		$this->assertInstanceOf(
			\FAIR\Packages\ReleaseDocument::class,
			$releases[ $this->test_did ],
			'Cached value should be ReleaseDocument.'
		);
	}

	/**
	 * Test should retain existing releases when adding new one.
	 */
	public function test_should_retain_existing_releases() {
		// Pre-seed with existing release.
		$existing = [ 'did:plc:existing' => 'placeholder' ];
		set_site_transient( CACHE_RELEASE_PACKAGES, $existing );

		$this->seed_pipeline();
		add_package_to_release_cache( $this->test_did );

		$releases = get_site_transient( CACHE_RELEASE_PACKAGES );
		$this->assertArrayHasKey( 'did:plc:existing', $releases, 'Existing releases should be retained.' );
		$this->assertArrayHasKey( $this->test_did, $releases, 'New release should be added.' );
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
class MaybeAddAcceptHeaderTest extends WP_UnitTestCase {

	private string $github_url = 'https://api.github.com/repos/test/repo/releases/assets/123';

	public function tear_down() {
		delete_site_transient( CACHE_RELEASE_PACKAGES );
		parent::tear_down();
	}

	/**
	 * Test should not modify args for non-GitHub URLs.
	 */
	public function test_should_not_modify_non_github_urls() {
		set_site_transient( CACHE_RELEASE_PACKAGES, [
			'did:plc:test' => $this->release_with_url( $this->github_url, 'application/octet-stream' ),
		] );

		$original_args = [ 'headers' => [ 'User-Agent' => 'Test' ] ];
		$actual        = maybe_add_accept_header( $original_args, 'https://example.com/download.zip' );

		$this->assertSame( $original_args, $actual, 'Non-GitHub URL should not be modified.' );
	}

	/**
	 * Test should add Accept header for octet-stream GitHub releases.
	 */
	public function test_should_add_accept_header_for_octet_stream() {
		set_site_transient( CACHE_RELEASE_PACKAGES, [
			'did:plc:test' => $this->release_with_url( $this->github_url, 'application/octet-stream' ),
		] );

		$args   = [ 'headers' => [ 'User-Agent' => 'Test' ] ];
		$actual = maybe_add_accept_header( $args, $this->github_url );

		$this->assertArrayHasKey( 'Accept', $actual['headers'] ?? [], 'Accept header should be added.' );
		$this->assertSame( 'application/octet-stream', $actual['headers']['Accept'], 'Should use octet-stream.' );
	}

	/**
	 * Test should not add Accept header for non-octet-stream releases.
	 */
	public function test_should_not_add_header_for_non_octet_stream() {
		set_site_transient( CACHE_RELEASE_PACKAGES, [
			'did:plc:test' => $this->release_with_url( $this->github_url, 'application/zip' ),
		] );

		$args   = [ 'headers' => [ 'User-Agent' => 'Test' ] ];
		$actual = maybe_add_accept_header( $args, $this->github_url );

		$this->assertArrayNotHasKey( 'Accept', $actual['headers'] ?? [], 'Should not add Accept for zip releases.' );
	}

	/**
	 * Test should not modify args when no release in cache matches URL.
	 */
	public function test_should_not_modify_when_url_not_in_cache() {
		set_site_transient( CACHE_RELEASE_PACKAGES, [
			'did:plc:test' => $this->release_with_url( 'https://api.github.com/repos/other/release.zip', 'application/octet-stream' ),
		] );

		$original_args = [ 'headers' => [ 'User-Agent' => 'Test' ] ];
		$actual        = maybe_add_accept_header( $original_args, 'https://api.github.com/repos/unrelated/release.zip' );

		$this->assertSame( $original_args, $actual, 'Unmatched URL should not modify args.' );
	}

	/**
	 * Test should handle empty release cache.
	 */
	public function test_should_handle_empty_cache() {
		delete_site_transient( CACHE_RELEASE_PACKAGES );

		$original_args = [ 'headers' => [ 'User-Agent' => 'Test' ] ];
		$actual        = maybe_add_accept_header( $original_args, $this->github_url );

		$this->assertSame( $original_args, $actual, 'Empty cache should not modify args.' );
	}

	/**
	 * Create a ReleaseDocument-like object with a package URL and content type.
	 */
	private function release_with_url( string $url, string $content_type ): \FAIR\Packages\ReleaseDocument {
		$data = (object) [
			'version'   => '1.0.0',
			'artifacts' => (object) [
				'package' => [
					(object) [
						'url'          => $url,
						'content-type' => $content_type,
					],
				],
			],
		];
		return \FAIR\Packages\ReleaseDocument::from_data( $data );
	}
}

/**
 * Tests for FAIR\Packages\search_by_did().
 *
 * @covers FAIR\Packages\search_by_did
 */
class SearchByDidTest extends WP_UnitTestCase {

	private string $test_did = 'did:plc:z72i7hdynmk6r22z27h6tvur';

	public function tear_down() {
		delete_site_transient( CACHE_METADATA_DOCUMENTS . $this->test_did );
		delete_site_transient( CACHE_UPDATE_ERRORS . $this->test_did );
		delete_site_transient( 'fair_did_error_' . $this->test_did );
		remove_all_filters( 'pre_http_request' );
		parent::tear_down();
	}

	/**
	 * Test should return original result when action is not query_plugins.
	 */
	public function test_should_return_result_for_non_plugin_action() {
		$result = 'original';

		$actual = search_by_did( $result, 'some_other_action', (object) [ 'search' => $this->test_did ] );

		$this->assertSame( $result, $actual, 'Should return original for non-plugin action.' );
	}

	/**
	 * Test should return original result when search is empty.
	 */
	public function test_should_return_result_for_empty_search() {
		$result = 'original';

		$actual = search_by_did( $result, 'query_plugins', (object) [ 'search' => '' ] );

		$this->assertSame( $result, $actual, 'Should return original for empty search.' );
	}

	/**
	 * Test should return original result when search is not a DID.
	 */
	public function test_should_return_result_for_non_did_search() {
		$result = [ 'plugins' => [] ];

		$actual = search_by_did( $result, 'query_plugins', (object) [ 'search' => 'my-plugin' ] );

		$this->assertSame( $result, $actual, 'Should return original for non-DID search.' );
	}

	/**
	 * Test should return original result when DID is wrong length.
	 */
	public function test_should_return_result_for_wrong_length_did() {
		$result = [ 'plugins' => [] ];

		$actual = search_by_did(
			$result,
			'query_plugins',
			(object) [ 'search' => 'did:plc:short' ]
		);

		$this->assertSame( $result, $actual, 'Should return original for too-short DID.' );
	}

	/**
	 * Test should return original result when pipeline fails.
	 */
	public function test_should_return_result_when_pipeline_fails() {
		// No DID document seeded → fetch failure.
		$result = [ 'plugins' => [] ];

		$actual = search_by_did(
			$result,
			'query_plugins',
			(object) [ 'search' => urlencode( $this->test_did ) ]
		);

		$this->assertSame( $result, $actual, 'Should return original when pipeline fails.' );
	}

	/**
	 * Test should return plugin search result when pipeline succeeds.
	 */
	public function test_should_return_plugin_result_on_success() {
		$this->seed_full_pipeline();

		$result = [ 'plugins' => [] ];
		$actual = search_by_did(
			$result,
			'query_plugins',
			(object) [ 'search' => urlencode( $this->test_did ) ]
		);

		$this->assertIsObject( $actual, 'Should return object.' );
		$this->assertObjectHasProperty( 'plugins', $actual, 'Result should have plugins property.' );
		$this->assertCount( 1, $actual->plugins, 'Should have one plugin.' );
		$this->assertObjectHasProperty( 'info', $actual, 'Result should have info property.' );
	}

	private function seed_full_pipeline(): void {
		set_site_transient( CACHE_METADATA_DOCUMENTS . $this->test_did, [
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

		add_filter( 'pre_http_request', function () {
			return [
				'body'     => json_encode( (object) [
					'id'       => $this->test_did,
					'name'     => 'Test Plugin',
					'slug'     => 'test-plugin',
					'type'     => 'wp-plugin',
					'filename' => 'test-plugin/test-plugin.php',
					'license'  => 'GPL-2.0-only',
					'authors'  => [ (object) [ 'name' => 'Author', 'url' => 'https://example.com' ] ],
					'security' => [],
					'releases' => [
						(object) [
							'version'   => '1.5.0',
							'artifacts' => (object) [
								'package' => [
									(object) [ 'url' => 'https://github.com/repo/release.zip' ],
								],
							],
						],
					],
				] ),
				'response' => [ 'code' => 200 ],
				'headers'  => [],
			];
		}, 10, 0 );
	}
}

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
