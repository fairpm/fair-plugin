<?php
/**
 * Tests for FAIR\Packages\MetadataDocument::from_response().
 *
 * @package FAIR
 */

use FAIR\Packages\MetadataDocument;
use FAIR\Tests\Factory\MetadataDocumentFactory;

/**
 * Tests for FAIR\Packages\MetadataDocument::from_response().
 *
 * @covers FAIR\Packages\MetadataDocument::from_response
 */
class MetadataDocumentFromResponseTest extends WP_UnitTestCase {

	/**
	 * Test should create document from valid response array.
	 */
	public function test_should_create_document_from_valid_response() {
		$response = [
			'body'    => json_encode( MetadataDocumentFactory::raw_data( 'metadata-doc-minimal' ) ),
			'headers' => [ 'X-Cache' => 'HIT' ],
		];

		$doc = MetadataDocument::from_response( $response );

		$this->assertInstanceOf( MetadataDocument::class, $doc, 'Should create MetadataDocument.' );
		$this->assertArrayHasKey( 'X-Cache', $doc->_headers, 'Headers should be stored.' );
		$this->assertSame( 'HIT', $doc->_headers['X-Cache'], 'Header value should match.' );
	}

	/**
	 * Test should return WP_Error for invalid JSON body.
	 */
	public function test_should_return_error_for_invalid_json() {
		$response = [
			'body'    => 'not-valid-json{',
			'headers' => [],
		];

		$actual = MetadataDocument::from_response( $response );

		$this->assertWPError( $actual, 'Invalid JSON should return WP_Error.' );
		$this->assertSame(
			'fair.packages.fetch_repository.invalid_json',
			$actual->get_error_code(),
			'Error code should indicate invalid JSON.'
		);
	}

	/**
	 * Test should return WP_Error when valid JSON contains invalid data.
	 */
	public function test_should_return_error_for_valid_json_with_invalid_data() {
		$invalid_data = (object) [
			'id' => 'did:plc:test',
			// Missing: type, license, authors, security
		];
		$response = [
			'body'    => json_encode( $invalid_data ),
			'headers' => [],
		];

		$actual = MetadataDocument::from_response( $response );

		$this->assertWPError( $actual, 'Valid JSON with invalid data should return WP_Error.' );
	}

	/**
	 * Test should handle empty body.
	 */
	public function test_should_return_error_for_empty_body() {
		$response = [
			'body'    => '',
			'headers' => [],
		];

		$actual = MetadataDocument::from_response( $response );

		$this->assertWPError( $actual, 'Empty body should return WP_Error.' );
	}

	/**
	 * Test should handle null body.
	 *
	 * NOTE: json_decode('null') returns null, not an object.
	 * from_response() passes null to from_data() which requires stdClass,
	 * triggering a TypeError. This is a known edge case in the parser.
	 */
	public function test_should_error_on_null_body() {
		$response = [
			'body'    => 'null',
			'headers' => [],
		];

		$this->expectException( \TypeError::class );
		MetadataDocument::from_response( $response );
	}
}
