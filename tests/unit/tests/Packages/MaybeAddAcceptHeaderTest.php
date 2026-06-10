<?php
/**
 * Tests for FAIR\Packages\maybe_add_accept_header().
 *
 * @package FAIR
 */

use function FAIR\Packages\maybe_add_accept_header;
use const FAIR\Packages\CACHE_RELEASE_PACKAGES;

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
