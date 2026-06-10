<?php
/**
 * Tests for FAIR\Salts\define_salt_keynames().
 *
 * @package FAIR
 */

use function FAIR\Salts\define_salt_keynames;

/**
 * Tests for FAIR\Salts\define_salt_keynames().
 *
 * @covers FAIR\Salts\define_salt_keynames
 */
class DefineSaltKeynamesTest extends WP_UnitTestCase {

	/**
	 * Test should return all 8 expected key names.
	 */
	public function test_should_return_8_key_names() {
		$keys = define_salt_keynames();

		$this->assertCount( 8, $keys, 'Should have 8 salt key names.' );
		$this->assertContains( 'AUTH_KEY', $keys );
		$this->assertContains( 'SECURE_AUTH_KEY', $keys );
		$this->assertContains( 'LOGGED_IN_KEY', $keys );
		$this->assertContains( 'NONCE_KEY', $keys );
		$this->assertContains( 'AUTH_SALT', $keys );
		$this->assertContains( 'SECURE_AUTH_SALT', $keys );
		$this->assertContains( 'LOGGED_IN_SALT', $keys );
		$this->assertContains( 'NONCE_SALT', $keys );
	}
}
