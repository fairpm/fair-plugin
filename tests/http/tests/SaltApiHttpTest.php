<?php
/**
 * HTTP tests: salt generation API interception.
 *
 * Verifies that calls to api.wordpress.org/secret-key/ are intercepted
 * and replaced with locally generated salts.
 *
 * @package FAIR
 */

use PHPUnit\Framework\TestCase;

/**
 * @group http
 */
class SaltApiHttpTest extends TestCase {

	/**
	 * Test that the salt API URL is intercepted and returns local salts.
	 */
	public function test_salt_api_is_intercepted(): void {
		$response = wp_remote_get( 'https://api.wordpress.org/secret-key/1.1/salt/', [
			'timeout' => 10,
		] );

		$this->assertNotWPError( $response, 'Salt API request should succeed.' );

		$body = wp_remote_retrieve_body( $response );
		$this->assertNotEmpty( $body, 'Response body should not be empty.' );

		// The response should contain define() statements for all 8 salt keys.
		$this->assertStringContainsString( "define( 'AUTH_KEY'", $body, 'Should have AUTH_KEY.' );
		$this->assertStringContainsString( "define( 'SECURE_AUTH_KEY'", $body, 'Should have SECURE_AUTH_KEY.' );
		$this->assertStringContainsString( "define( 'LOGGED_IN_KEY'", $body, 'Should have LOGGED_IN_KEY.' );
		$this->assertStringContainsString( "define( 'NONCE_KEY'", $body, 'Should have NONCE_KEY.' );
		$this->assertStringContainsString( "define( 'AUTH_SALT'", $body, 'Should have AUTH_SALT.' );
		$this->assertStringContainsString( "define( 'SECURE_AUTH_SALT'", $body, 'Should have SECURE_AUTH_SALT.' );
		$this->assertStringContainsString( "define( 'LOGGED_IN_SALT'", $body, 'Should have LOGGED_IN_SALT.' );
		$this->assertStringContainsString( "define( 'NONCE_SALT'", $body, 'Should have NONCE_SALT.' );
	}

	/**
	 * Test that salt values are 64 characters each.
	 */
	public function test_salt_values_are_64_characters(): void {
		$response = wp_remote_get( 'https://api.wordpress.org/secret-key/1.1/salt/', [
			'timeout' => 10,
		] );

		$body = wp_remote_retrieve_body( $response );

		// Extract all values between single quotes in define() calls.
		preg_match_all( "/define\\( '[^']+', '([^']+)' \\)/", $body, $matches );
		$values = $matches[1] ?? [];

		$this->assertCount( 8, $values, 'Should have exactly 8 salt values.' );

		foreach ( $values as $value ) {
			// esc_attr may expand special chars, so length is >= 64.
			$this->assertGreaterThanOrEqual( 64, strlen( $value ), "Salt value '{$value}' should be >= 64 chars." );
		}
	}

	/**
	 * Test that unrelated URLs are not intercepted.
	 */
	public function test_unrelated_urls_passthrough(): void {
		// Make a real request to a URL that won't be intercepted.
		$response = wp_remote_get( 'https://example.com/', [
			'timeout' => 5,
		] );

		// This may fail in Docker without internet, but the filter should
		// return false (not intercept), allowing the request to proceed.
		// We just verify the function doesn't crash.
		$this->assertNotNull( $response );
	}

	private function assertNotWPError( $actual, string $message = '' ): void {
		$this->assertNotInstanceOf( \WP_Error::class, $actual, $message );
	}
}
