<?php
/**
 * Tests for FAIR\Salts\get_salt_generation_response().
 *
 * @package FAIR
 */

use function FAIR\Salts\get_salt_generation_response;

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
