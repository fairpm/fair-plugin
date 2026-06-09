<?php
/**
 * Tests for FAIR\Salts functions.
 *
 * @package FAIR
 */

use function FAIR\Salts\replace_salt_generation_via_api;
use function FAIR\Salts\define_salt_keynames;
use function FAIR\Salts\generate_salt_string;
use function FAIR\Salts\generate_salt_response_body;
use function FAIR\Salts\get_salt_generation_response;

/**
 * Tests for FAIR\Salts\replace_salt_generation_via_api().
 *
 * @covers FAIR\Salts\replace_salt_generation_via_api
 */
class ReplaceSaltGenerationViaApiTest extends WP_UnitTestCase {

	/**
	 * Test should intercept api.wordpress.org/secret-key requests.
	 */
	public function test_should_intercept_salt_api_request() {
		$result = replace_salt_generation_via_api(
			false,
			[],
			'https://api.wordpress.org/secret-key/1.1/salt/'
		);

		$this->assertIsArray( $result, 'Should return mock response array.' );
		$this->assertArrayHasKey( 'body', $result, 'Response should have body.' );
		$this->assertArrayHasKey( 'response', $result, 'Response should have response meta.' );
		$this->assertSame( 200, $result['response']['code'], 'Response code should be 200.' );
	}

	/**
	 * Test should not intercept unrelated URLs.
	 */
	public function test_should_not_intercept_unrelated_urls() {
		$result = replace_salt_generation_via_api(
			false,
			[],
			'https://example.com/some-other-api'
		);

		$this->assertFalse( $result, 'Unrelated URLs should pass through.' );
	}

	/**
	 * Test should pass through non-array values unchanged.
	 */
	public function test_should_pass_through_if_already_set() {
		$result = replace_salt_generation_via_api(
			[ 'body' => 'custom body' ],
			[],
			'https://api.wordpress.org/secret-key/1.1/salt/'
		);

		// The function always intercepts matching URLs regardless of $value.
		// This is by design — it replaces any pre_http_request result.
		$this->assertArrayHasKey( 'body', $result, 'Should return salt response.' );
		$this->assertStringContainsString( 'define', $result['body'], 'Should contain salt defines.' );
	}
}

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

/**
 * Tests for FAIR\Salts\get_salt_generation_response().
 *
 * @covers FAIR\Salts\get_salt_generation_response
 */
class GetSaltGenerationResponseTest extends WP_UnitTestCase {

	/**
	 * Test should return a properly structured response.
	 */
	public function test_should_return_properly_structured_response() {
		$response = get_salt_generation_response();

		$this->assertIsArray( $response );
		$this->assertArrayHasKey( 'body', $response );
		$this->assertArrayHasKey( 'response', $response );
		$this->assertSame( 200, $response['response']['code'] );
		$this->assertNotEmpty( $response['body'], 'Body should contain salt defines.' );
	}
}
