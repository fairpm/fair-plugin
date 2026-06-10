<?php
/**
 * HTTP tests: avatar URL filtering.
 *
 * Tests the pure filter functions directly rather than relying
 * on WordPress hook registration timing.
 *
 * @package FAIR
 */

use PHPUnit\Framework\TestCase;

/**
 * @group http
 */
class AvatarHttpTest extends TestCase {

	/**
	 * Test that generate_default_avatar returns an SVG data URI.
	 */
	public function test_generate_default_avatar_returns_data_uri(): void {
		$result = \FAIR\Avatars\generate_default_avatar( 'Test User' );

		$this->assertStringStartsWith( 'data:image/svg+xml;base64,', $result, 'Should be a data URI.' );
	}

	/**
	 * Test avatar alt text generation.
	 */
	public function test_get_avatar_alt_for_user(): void {
		$user_id = wp_insert_user( [
			'user_login'   => 'avatar_alt_test',
			'user_pass'    => 'password',
			'display_name' => 'Alt Test User',
			'user_email'   => 'alt-test@example.com',
			'role'         => 'subscriber',
		] );

		if ( is_wp_error( $user_id ) ) {
			$this->markTestSkipped( 'Could not create test user.' );
			return;
		}

		$alt = \FAIR\Avatars\get_avatar_alt( $user_id );
		$this->assertStringContainsString( 'Alt Test User', $alt, 'Alt should include display name.' );

		wp_delete_user( $user_id );
	}
}
