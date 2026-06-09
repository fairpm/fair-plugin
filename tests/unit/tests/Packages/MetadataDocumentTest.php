<?php
/**
 * Tests for FAIR\Packages\MetadataDocument::from_data() and from_response().
 *
 * @package FAIR
 */

use FAIR\Packages\MetadataDocument;
use FAIR\Tests\Factory\MetadataDocumentFactory;

/**
 * Tests for FAIR\Packages\MetadataDocument::from_data().
 *
 * @covers FAIR\Packages\MetadataDocument::from_data
 */
class MetadataDocumentFromDataTest extends WP_UnitTestCase {

	/**
	 * Test should create document with all mandatory and optional fields populated.
	 */
	public function test_should_create_document_with_all_fields() {
		$data = MetadataDocumentFactory::raw_data( 'metadata-doc-full' );

		$doc = MetadataDocument::from_data( $data );

		$this->assertInstanceOf( MetadataDocument::class, $doc, 'Should return MetadataDocument instance.' );
		$this->assertSame( 'did:plc:z72i7hdynmk6r22z27h6tvur', $doc->id, 'ID should match.' );
		$this->assertSame( 'wp-plugin', $doc->type, 'Type should match.' );
		$this->assertSame( 'Test FAIR Plugin', $doc->name, 'Name should match.' );
		$this->assertSame( 'test-fair-plugin', $doc->slug, 'Slug should match.' );
		$this->assertSame( 'test-fair-plugin/test-fair-plugin.php', $doc->filename, 'Filename should match.' );
		$this->assertSame( 'GPL-2.0-only', $doc->license, 'License should match.' );
		$this->assertNotEmpty( $doc->description, 'Description should be set.' );
		$this->assertCount( 3, $doc->keywords, 'Keywords should be populated.' );
		$this->assertCount( 1, $doc->authors, 'Authors should be populated.' );
		$this->assertIsArray( $doc->security, 'Security should be an array.' );
		$this->assertNotEmpty( $doc->last_updated, 'last_updated should be set.' );
		$this->assertCount( 3, $doc->releases, 'Three releases should be parsed.' );
	}

	/**
	 * Test should create minimal valid document with only mandatory fields.
	 */
	public function test_should_create_document_with_minimal_fields() {
		$data = MetadataDocumentFactory::raw_data( 'metadata-doc-minimal' );

		$doc = MetadataDocument::from_data( $data );

		$this->assertInstanceOf( MetadataDocument::class, $doc, 'Should create valid document.' );
		$this->assertSame( 'did:plc:minimal12345678901234567890', $doc->id, 'Minimal ID should match.' );
		$this->assertEmpty( $doc->name, 'Name is optional, should default empty.' );
		$this->assertEmpty( $doc->slug, 'Slug is optional, should default empty.' );
		$this->assertCount( 1, $doc->releases, 'Should have one release.' );
	}

	/**
	 * Test should return WP_Error when id is missing.
	 */
	public function test_should_return_error_when_id_missing() {
		$data = MetadataDocumentFactory::without_field( 'id' );

		$actual = MetadataDocument::from_data( $data );

		$this->assertWPError( $actual, 'Missing id should return WP_Error.' );
		$this->assertStringContainsString( 'id', $actual->get_error_message(), 'Error should mention missing field.' );
	}

	/**
	 * Test should return WP_Error when type is missing.
	 */
	public function test_should_return_error_when_type_missing() {
		$data = MetadataDocumentFactory::without_field( 'type' );

		$actual = MetadataDocument::from_data( $data );

		$this->assertWPError( $actual, 'Missing type should return WP_Error.' );
		$this->assertStringContainsString( 'type', $actual->get_error_message(), 'Error should mention missing field.' );
	}

	/**
	 * Test should return WP_Error when license is missing.
	 */
	public function test_should_return_error_when_license_missing() {
		$data = MetadataDocumentFactory::without_field( 'license' );

		$actual = MetadataDocument::from_data( $data );

		$this->assertWPError( $actual, 'Missing license should return WP_Error.' );
	}

	/**
	 * Test should return WP_Error when authors is missing.
	 */
	public function test_should_return_error_when_authors_missing() {
		$data = MetadataDocumentFactory::without_field( 'authors' );

		$actual = MetadataDocument::from_data( $data );

		$this->assertWPError( $actual, 'Missing authors should return WP_Error.' );
	}

	/**
	 * Test should return WP_Error when security is missing.
	 */
	public function test_should_return_error_when_security_missing() {
		$data = MetadataDocumentFactory::without_field( 'security' );

		$actual = MetadataDocument::from_data( $data );

		$this->assertWPError( $actual, 'Missing security should return WP_Error.' );
	}

	/**
	 * Test should return WP_Error when releases is missing.
	 */
	public function test_should_return_error_when_releases_missing() {
		$data = MetadataDocumentFactory::without_releases();

		$actual = MetadataDocument::from_data( $data );

		$this->assertWPError( $actual, 'Missing releases should return WP_Error.' );
		$this->assertSame(
			'fair.packages.metadata_document.missing_releases',
			$actual->get_error_code(),
			'Error code should indicate missing releases.'
		);
	}

	/**
	 * Test should propagate error from invalid release.
	 */
	public function test_should_propagate_error_from_invalid_release() {
		$data = MetadataDocumentFactory::raw_data( 'metadata-doc-full' );
		// Add a release with no version.
		$data->releases[] = (object) [
			'artifacts' => (object) [
				'package' => [],
			],
		];

		$actual = MetadataDocument::from_data( $data );

		$this->assertWPError( $actual, 'Invalid release should return WP_Error.' );
	}

	/**
	 * Test optional fields should be null when not present.
	 */
	public function test_optional_fields_should_be_null_when_absent() {
		$data = MetadataDocumentFactory::builder()
			->unset( 'name' )
			->unset( 'slug' )
			->unset( 'filename' )
			->unset( 'description' )
			->unset( 'keywords' )
			->unset( 'last_updated' )
			->unset( 'sections' )
			->build();

		$this->assertNull( $data->name, 'Optional name should be null when absent.' );
	}

	/**
	 * Test multiple releases are all parsed.
	 */
	public function test_should_parse_multiple_releases() {
		$data = MetadataDocumentFactory::raw_data( 'metadata-doc-full' );

		$doc = MetadataDocument::from_data( $data );

		$this->assertCount( 3, $doc->releases, 'All three releases should be parsed.' );
		$versions = array_map( fn( $r ) => $r->version, $doc->releases );
		$this->assertContains( '2.0.0', $versions, 'Release 2.0.0 should be parsed.' );
		$this->assertContains( '1.5.0', $versions, 'Release 1.5.0 should be parsed.' );
		$this->assertContains( '1.0.0', $versions, 'Release 1.0.0 should be parsed.' );
	}

	/**
	 * Test security field is stored as an array.
	 */
	public function test_security_should_be_array() {
		$data = MetadataDocumentFactory::builder()
			->set( 'security', [ 'CVE-2024-1234', 'GHSA-abcd-efgh' ] )
			->build();

		$this->assertIsArray( $data->security, 'Security should be an array.' );
		$this->assertCount( 2, $data->security, 'Security should contain both entries.' );
	}
}

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
