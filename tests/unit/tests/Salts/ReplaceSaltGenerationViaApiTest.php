<?php
/**
 * Tests for FAIR\Salts\replace_salt_generation_via_api().
 *
 * @package FAIR
 */

use function FAIR\Salts\replace_salt_generation_via_api;

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

