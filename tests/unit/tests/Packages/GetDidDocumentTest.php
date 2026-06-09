<?php
/**
 * Tests for FAIR\Packages\get_did_document().
 *
 * @package FAIR
 */

use function FAIR\Packages\get_did_document;
use function FAIR\Packages\cache_update_error;
use function FAIR\Packages\clear_update_error;
use const FAIR\Packages\CACHE_METADATA_DOCUMENTS;
use const FAIR\Packages\CACHE_UPDATE_ERRORS;

/**
 * Tests for FAIR\Packages\get_did_document().
 *
 * @covers FAIR\Packages\get_did_document
 */
class GetDidDocumentTest extends WP_UnitTestCase {

	private string $test_did = 'did:plc:z72i7hdynmk6r22z27h6tvur';

	/**
	 * Tear down: remove all transients and HTTP filters.
	 */
	public function tear_down() {
		delete_site_transient( CACHE_METADATA_DOCUMENTS . $this->test_did );
		delete_site_transient( CACHE_METADATA_DOCUMENTS . 'did:plc:ppicmk23c5pimdivve34bcp2' );
		delete_site_transient( CACHE_UPDATE_ERRORS . $this->test_did );
		remove_all_filters( 'pre_http_request' );
		parent::tear_down();
	}

	/**
	 * Test should return cached document when available.
	 */
	public function test_should_return_cached_document() {
		$cached_doc = [
			'id'                 => $this->test_did,
			'alsoKnownAs'        => [],
			'verificationMethod' => [],
			'service'            => [],
		];
		set_site_transient( CACHE_METADATA_DOCUMENTS . $this->test_did, $cached_doc, HOUR_IN_SECONDS );

		$actual = get_did_document( $this->test_did );

		$this->assertSame( $cached_doc, $actual, 'Should return cached DID document.' );
	}

	/**
	 * Test should return cached error from previous failed attempt.
	 */
	public function test_should_return_cached_error() {
		$cached_error = new WP_Error( 'fair.packages.did.fetch_error', 'Cached failure' );
		set_site_transient( CACHE_UPDATE_ERRORS . $this->test_did, $cached_error, HOUR_IN_SECONDS );

		$actual = get_did_document( $this->test_did );

		$this->assertWPError( $actual, 'Should return cached error.' );
		$this->assertSame(
			'fair.packages.did.fetch_error',
			$actual->get_error_code(),
			'Error code should match cached error.'
		);
	}

	/**
	 * Test should propagate parse_did error and cache it.
	 */
	public function test_should_propagate_parse_error() {
		$actual = get_did_document( 'not-a-did' );

		$this->assertWPError( $actual, 'Invalid DID should return WP_Error.' );

		// Error should be cached.
		$cached = get_site_transient( CACHE_UPDATE_ERRORS . 'not-a-did' );
		$this->assertWPError( $cached, 'Parse error should be cached.' );
	}
}
