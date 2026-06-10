<?php
/**
 * Tests for FAIR\Packages\get_did_hash().
 *
 * @package FAIR
 */

use function FAIR\Packages\get_did_hash;

/**
 * Tests for FAIR\Packages\get_did_hash().
 *
 * @covers FAIR\Packages\get_did_hash
 */
class GetDidHashTest extends WP_UnitTestCase {

	/**
	 * Test should return a 6-character hex string for a valid DID.
	 */
	public function test_should_return_6_char_hex_string() {
		$did = 'did:plc:z72i7hdynmk6r22z27h6tvur';
		$actual = get_did_hash( $did );

		$this->assertIsString( $actual, 'Expected a string hash.' );
		$this->assertSame( 6, strlen( $actual ), 'Hash should be exactly 6 characters.' );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{6}$/', $actual, 'Hash should be lowercase hex.' );
	}

	/**
	 * Test should return the same hash for the same DID.
	 */
	public function test_should_be_deterministic() {
		$did = 'did:plc:z72i7hdynmk6r22z27h6tvur';

		$this->assertSame(
			get_did_hash( $did ),
			get_did_hash( $did ),
			'Same DID should produce the same hash.'
		);
	}

	/**
	 * Test should return different hashes for different DIDs.
	 */
	public function test_should_produce_different_hashes_for_different_dids() {
		$hash1 = get_did_hash( 'did:plc:z72i7hdynmk6r22z27h6tvur' );
		$hash2 = get_did_hash( 'did:plc:ppicmk23c5pimdivve34bcp2' );

		$this->assertNotSame( $hash1, $hash2, 'Different DIDs should produce different hashes.' );
	}

	/**
	 * Test should propagate WP_Error from parse_did for invalid input.
	 */
	public function test_should_return_wp_error_for_invalid_did() {
		$actual = get_did_hash( 'not-a-did' );

		$this->assertWPError( $actual, 'Expected WP_Error for invalid DID.' );
		$this->assertSame(
			'fair.packages.validate_did.not_did',
			$actual->get_error_code(),
			'Error code should indicate not a DID.'
		);
	}

	/**
	 * Test should propagate WP_Error for empty string.
	 */
	public function test_should_return_wp_error_for_empty_string() {
		$actual = get_did_hash( '' );

		$this->assertWPError( $actual, 'Expected WP_Error for empty string.' );
	}

	/**
	 * Test should handle a 32-character DID.
	 *
	 * DIDs are at minimum 32 characters (did:plc: + 24-char identifier).
	 */
	public function test_should_hash_32_character_did() {
		$did = 'did:plc:abcdefgh12345678abcdefgh';
		$actual = get_did_hash( $did );

		$this->assertIsString( $actual, 'Expected a valid hash for 32-char DID.' );
		$this->assertSame( 6, strlen( $actual ), 'Hash should be 6 characters.' );
	}

	/**
	 * Test should handle a long DID.
	 */
	public function test_should_hash_long_did() {
		$did = 'did:plc:' . str_repeat( 'x', 100 );
		$actual = get_did_hash( $did );

		$this->assertIsString( $actual, 'Expected a valid hash for long DID.' );
		$this->assertSame( 6, strlen( $actual ), 'Hash should be 6 characters.' );
	}

	/**
	 * Test should propagate error for non-plc DID method.
	 */
	public function test_should_return_wp_error_for_non_plc_method() {
		$actual = get_did_hash( 'did:web:example.com' );

		$this->assertWPError( $actual, 'Expected WP_Error for non-plc method.' );
	}
}
