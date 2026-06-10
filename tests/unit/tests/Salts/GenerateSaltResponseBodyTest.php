<?php
/**
 * Tests for FAIR\Salts\generate_salt_response_body().
 *
 * @package FAIR
 */

use function FAIR\Salts\generate_salt_response_body;

/**
 * Tests for FAIR\Salts\generate_salt_response_body().
 *
 * @covers FAIR\Salts\generate_salt_response_body
 */
class GenerateSaltResponseBodyTest extends WP_UnitTestCase {

	/**
	 * Test should generate define() statements for all 8 keys.
	 */
	public function test_should_generate_all_8_defines() {
		$body = generate_salt_response_body();

		$this->assertStringContainsString( 'define( \'AUTH_KEY\'', $body );
		$this->assertStringContainsString( 'define( \'SECURE_AUTH_KEY\'', $body );
		$this->assertStringContainsString( 'define( \'LOGGED_IN_KEY\'', $body );
		$this->assertStringContainsString( 'define( \'NONCE_KEY\'', $body );
		$this->assertStringContainsString( 'define( \'AUTH_SALT\'', $body );
		$this->assertStringContainsString( 'define( \'SECURE_AUTH_SALT\'', $body );
		$this->assertStringContainsString( 'define( \'LOGGED_IN_SALT\'', $body );
		$this->assertStringContainsString( 'define( \'NONCE_SALT\'', $body );
	}

	/**
	 * Test should end each line with newline.
	 */
	public function test_should_have_8_lines() {
		$body  = generate_salt_response_body();
		$lines = explode( "\n", trim( $body ) );

		$this->assertCount( 8, $lines, 'Should have exactly 8 define() lines.' );
	}
}
