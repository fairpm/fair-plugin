<?php
/**
 * Tests for FAIR\Packages\parse_did().
 *
 * @package FAIR
 */

use function FAIR\Packages\parse_did;

/**
 * Tests for FAIR\Packages\parse_did().
 *
 * @covers FAIR\Packages\parse_did
 */
class ParseDidTest extends WP_UnitTestCase {

	/**
	 * Test should return the DID string for a valid did:plc: DID.
	 */
	public function test_should_return_string_for_valid_plc_did() {
		$did = 'did:plc:z72i7hdynmk6r22z27h6tvur';
		$actual = parse_did( $did );

		$this->assertIsString( $actual, 'Expected a string for a valid DID.' );
		$this->assertSame( $did, $actual, 'The returned DID should match the input.' );
	}

	/**
	 * Test should return WP_Error for non-DID string.
	 */
	public function test_should_return_error_for_non_did_string() {
		$actual = parse_did( 'not-a-did' );

		$this->assertWPError( $actual, 'Expected WP_Error for a non-DID string.' );
		$this->assertSame( 'fair.packages.validate_did.not_did', $actual->get_error_code(), 'Error code should indicate not a DID.' );
	}

	/**
	 * Test should return the DID string even with minimal method-specific-id.
	 *
	 * Parse_did() only validates format, not content.
	 */
	public function test_should_return_string_for_minimal_plc_did() {
		$did = 'did:plc:x';
		$actual = parse_did( $did );

		$this->assertIsString( $actual, 'Expected a string for a minimal DID.' );
		$this->assertSame( $did, $actual, 'The returned DID should match the input.' );
	}

	/**
	 * Test should return WP_Error for non-plc DID method.
	 */
	public function test_should_return_error_for_non_plc_method() {
		$actual = parse_did( 'did:web:example.com' );

		$this->assertWPError( $actual, 'Expected WP_Error for non-plc DID method.' );
		$this->assertSame( 'fair.packages.validate_did.not_did', $actual->get_error_code(), 'Non-plc methods should be rejected.' );
	}

	/**
	 * Test should return WP_Error for empty string.
	 */
	public function test_should_return_error_for_empty_string() {
		$actual = parse_did( '' );

		$this->assertWPError( $actual, 'Expected WP_Error for empty string.' );
	}

	/**
	 * Test should return WP_Error for partial DID.
	 */
	public function test_should_return_error_for_partial_did() {
		$actual = parse_did( 'did:plc' );

		$this->assertWPError( $actual, 'Expected WP_Error for partial DID.' );
	}
}
