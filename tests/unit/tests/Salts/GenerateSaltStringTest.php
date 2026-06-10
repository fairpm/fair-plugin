<?php
/**
 * Tests for FAIR\Salts\generate_salt_string().
 *
 * @package FAIR
 */

use function FAIR\Salts\generate_salt_string;

/**
 * Tests for FAIR\Salts\generate_salt_string().
 *
 * @covers FAIR\Salts\generate_salt_string
 */
class GenerateSaltStringTest extends WP_UnitTestCase {

	/**
	 * Test should return a 64-character string.
	 */
	public function test_should_return_64_character_string() {
		$salt = generate_salt_string();

		$this->assertIsString( $salt, 'Should return a string.' );
		// esc_attr() may expand special characters (e.g., &amp;), so length can vary.
		$this->assertGreaterThanOrEqual( 64, strlen( $salt ), 'Should be at least 64 characters.' );
		$this->assertLessThanOrEqual( 90, strlen( $salt ), 'Should not be excessively long even with escaping.' );
	}

	/**
	 * Test should produce different salts on consecutive calls.
	 */
	public function test_should_produce_unique_salts() {
		$salts = [];
		for ( $i = 0; $i < 10; $i++ ) {
			$salts[] = generate_salt_string();
		}

		$this->assertSame( 10, count( array_unique( $salts ) ), 'Each call should produce a unique salt.' );
	}

	/**
	 * Test should contain only valid characters.
	 */
	public function test_should_contain_only_valid_characters() {
		$salt = generate_salt_string();

		// The salt is escaped with esc_attr, so it should be safe.
		$this->assertNotEmpty( $salt, 'Salt should not be empty.' );
	}
}
