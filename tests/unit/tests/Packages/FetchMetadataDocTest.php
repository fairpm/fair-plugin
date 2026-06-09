<?php
/**
 * Tests for FAIR\Packages\fetch_metadata_doc().
 *
 * @package FAIR
 */

use function FAIR\Packages\fetch_metadata_doc;
use const FAIR\Packages\CACHE_KEY;
use const FAIR\Packages\CACHE_UPDATE_ERRORS;

/**
 * Tests for FAIR\Packages\fetch_metadata_doc().
 *
 * @covers FAIR\Packages\fetch_metadata_doc
 * @covers FAIR\Packages\fetch_metadata_from_local
 */
class FetchMetadataDocTest extends WP_UnitTestCase {

	private string $test_did = 'did:plc:z72i7hdynmk6r22z27h6tvur';
	private string $test_url = 'https://test.example.com/metadata/did:plc:z72i7hdynmk6r22z27h6tvur';
	private string $cache_key;

	public function set_up() {
		parent::set_up();
		$this->cache_key = CACHE_KEY . md5( $this->test_url );
	}

	/**
	 * Tear down: remove all transients and HTTP filters.
	 */
	public function tear_down() {
		delete_site_transient( $this->cache_key );
		delete_site_transient( CACHE_UPDATE_ERRORS . $this->test_did );
		delete_site_transient( 'fair-metadata-endpoint-' . $this->test_did );
		remove_all_filters( 'pre_http_request' );
		parent::tear_down();
	}

	/**
	 * Build minimal valid metadata response body.
	 */
	private function valid_metadata_body(): string {
		return json_encode( (object) [
			'id'       => $this->test_did,
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
		] );
	}

	/**
	 * Test should return cached response when available.
	 */
	public function test_should_return_cached_response() {
		$cached_response = [
			'body'    => $this->valid_metadata_body(),
			'headers' => [ 'X-Cache' => 'HIT' ],
		];
		set_site_transient( $this->cache_key, $cached_response, HOUR_IN_SECONDS );

		$actual = fetch_metadata_doc( $this->test_url, $this->test_did );

		$this->assertInstanceOf(
			\FAIR\Packages\MetadataDocument::class,
			$actual,
			'Should return MetadataDocument from cache.'
		);
	}

	/**
	 * Test should return WP_Error when HTTP request fails.
	 */
	public function test_should_return_error_on_http_failure() {
		add_filter( 'pre_http_request', function () {
			return new WP_Error( 'http_request_failed', 'Connection refused' );
		}, 10, 0 );

		$actual = fetch_metadata_doc( $this->test_url, $this->test_did );

		$this->assertWPError( $actual, 'HTTP failure should return WP_Error.' );

		// Error should be cached.
		$cached = get_site_transient( CACHE_UPDATE_ERRORS . $this->test_did );
		$this->assertWPError( $cached, 'HTTP error should be cached.' );
	}

	/**
	 * Test should return WP_Error for non-200 HTTP responses.
	 */
	public function test_should_return_error_for_non_200_response() {
		add_filter( 'pre_http_request', function () {
			return [
				'body'     => 'Not Found',
				'response' => [ 'code' => 404 ],
			];
		}, 10, 0 );

		$actual = fetch_metadata_doc( $this->test_url, $this->test_did );

		$this->assertWPError( $actual, 'Non-200 response should return WP_Error.' );
		$this->assertSame(
			'fair.packages.metadata.http_error',
			$actual->get_error_code(),
			'Error code should indicate HTTP error.'
		);
	}

	/**
	 * Test should cache successful HTTP responses.
	 */
	public function test_should_cache_successful_http_response() {
		add_filter( 'pre_http_request', function () {
			return [
				'body'     => $this->valid_metadata_body(),
				'response' => [ 'code' => 200 ],
				'headers'  => [],
			];
		}, 10, 0 );

		fetch_metadata_doc( $this->test_url, $this->test_did );

		$cached = get_site_transient( $this->cache_key );
		$this->assertNotFalse( $cached, 'HTTP response should be cached.' );
	}

	/**
	 * Test should return MetadataDocument from successful HTTP.
	 */
	public function test_should_return_metadata_from_http() {
		add_filter( 'pre_http_request', function () {
			return [
				'body'     => $this->valid_metadata_body(),
				'response' => [ 'code' => 200 ],
				'headers'  => [],
			];
		}, 10, 0 );

		$actual = fetch_metadata_doc( $this->test_url, $this->test_did );

		$this->assertInstanceOf(
			\FAIR\Packages\MetadataDocument::class,
			$actual,
			'Should return MetadataDocument from HTTP response.'
		);
	}
}
