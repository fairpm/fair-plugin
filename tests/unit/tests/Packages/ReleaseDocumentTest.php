<?php
/**
 * Tests for FAIR\Packages\ReleaseDocument::from_data().
 *
 * @package FAIR
 */

use FAIR\Packages\ReleaseDocument;
use FAIR\Tests\Factory\ReleaseDocumentFactory;

/**
 * Tests for FAIR\Packages\ReleaseDocument::from_data().
 *
 * @covers FAIR\Packages\ReleaseDocument::from_data
 */
class ReleaseDocumentFromDataTest extends WP_UnitTestCase {

	/**
	 * Test should create document with all fields populated.
	 */
	public function test_should_create_document_with_all_fields() {
		$doc = ReleaseDocumentFactory::full();

		$this->assertInstanceOf( ReleaseDocument::class, $doc, 'Should return ReleaseDocument instance.' );
		$this->assertSame( '1.0.0', $doc->version, 'Version should match.' );
		$this->assertIsObject( $doc->artifacts, 'Artifacts should be an object.' );

		// Check artifacts sub-objects.
		$this->assertObjectHasProperty( 'icon', $doc->artifacts, 'Artifacts should have icon.' );
		$this->assertObjectHasProperty( 'banner', $doc->artifacts, 'Artifacts should have banner.' );
		$this->assertObjectHasProperty( 'package', $doc->artifacts, 'Artifacts should have package.' );

		// Optional fields.
		$this->assertIsObject( $doc->requires, 'Requires should be an object when present.' );
		$this->assertIsObject( $doc->suggests, 'Suggests should be an object when present.' );
	}

	/**
	 * Test should create document with requirements populated.
	 */
	public function test_should_create_document_with_requirements() {
		$doc = ReleaseDocumentFactory::with_requirements();

		$this->assertSame( '2.0.0', $doc->version, 'Version should match.' );

		$requires = (array) $doc->requires;
		$this->assertArrayHasKey( 'env:php', $requires, 'Should have env:php requirement.' );
		$this->assertArrayHasKey( 'env:wp', $requires, 'Should have env:wp requirement.' );
		$this->assertArrayHasKey( 'env:php-gmp', $requires, 'Should have env:php-gmp requirement.' );

		$suggests = (array) $doc->suggests;
		$this->assertArrayHasKey( 'env:wp', $suggests, 'Should have env:wp suggestion.' );
	}

	/**
	 * Test should create document with specific version.
	 */
	public function test_should_create_document_with_specific_version() {
		$doc = ReleaseDocumentFactory::with_version( '3.5.7' );

		$this->assertSame( '3.5.7', $doc->version, 'Version should be set.' );
	}

	/**
	 * Test should return WP_Error when version is missing.
	 */
	public function test_should_return_error_when_version_missing() {
		$data = ReleaseDocumentFactory::without_field( 'version' );

		$actual = ReleaseDocument::from_data( $data );

		$this->assertWPError( $actual, 'Missing version should return WP_Error.' );
		$this->assertStringContainsString( 'version', $actual->get_error_message(), 'Error should mention missing field.' );
	}

	/**
	 * Test should return WP_Error when artifacts is missing.
	 */
	public function test_should_return_error_when_artifacts_missing() {
		$data = ReleaseDocumentFactory::without_field( 'artifacts' );

		$actual = ReleaseDocument::from_data( $data );

		$this->assertWPError( $actual, 'Missing artifacts should return WP_Error.' );
		$this->assertStringContainsString( 'artifacts', $actual->get_error_message(), 'Error should mention missing field.' );
	}

	/**
	 * Test optional fields should be null when not present.
	 */
	public function test_optional_fields_should_be_null_when_absent() {
		$data = (object) [
			'version'   => '1.0.0',
			'artifacts' => (object) [ 'package' => [] ],
		];

		$doc = ReleaseDocument::from_data( $data );

		$this->assertInstanceOf( ReleaseDocument::class, $doc, 'Should create document with minimal fields.' );
		$this->assertNull( $doc->provides, 'provides should be null when absent.' );
		$this->assertNull( $doc->requires, 'requires should be null when absent.' );
		$this->assertNull( $doc->suggests, 'suggests should be null when absent.' );
		$this->assertNull( $doc->auth, 'auth should be null when absent.' );
	}

	/**
	 * Test should create document without artifacts sub-object.
	 */
	public function test_should_handle_minimal_artifacts() {
		$data = (object) [
			'version'   => '1.0.0',
			'artifacts' => (object) [ 'package' => [] ],
		];

		$doc = ReleaseDocument::from_data( $data );

		$this->assertIsObject( $doc->artifacts, 'Artifacts should be an object.' );
	}

	/**
	 * Test should handle builder output with unset fields.
	 */
	public function test_should_handle_builder_with_unset_optional_fields() {
		$data = ReleaseDocumentFactory::builder()
			->unset( 'provides' )
			->unset( 'requires' )
			->unset( 'suggests' )
			->unset( 'auth' )
			->raw();

		$doc = ReleaseDocument::from_data( $data );

		$this->assertNull( $doc->provides, 'unset provides should be null.' );
		$this->assertNull( $doc->requires, 'unset requires should be null.' );
		$this->assertNull( $doc->suggests, 'unset suggests should be null.' );
		$this->assertNull( $doc->auth, 'unset auth should be null.' );
	}
}
