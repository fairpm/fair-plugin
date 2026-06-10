<?php
/**
 * Tests for FAIR\Avatars\get_avatar_alt().
 *
 * @package FAIR
 */

use function FAIR\Avatars\get_avatar_alt;

/**
 * Tests for FAIR\Avatars\get_avatar_alt().
 *
 * @covers FAIR\Avatars\get_avatar_alt
 */
class GetAvatarAltTest extends WP_UnitTestCase {

	/**
	 * Test should return alt text for a user by ID.
	 */
	public function test_should_return_alt_for_user_id() {
		$user_id = $this->factory->user->create( [ 'display_name' => 'Test User' ] );

		$alt = get_avatar_alt( $user_id );

		$this->assertStringContainsString( 'Test User', $alt, 'Alt should include user display name.' );
	}

	/**
	 * Test should return alt text for a WP_User object.
	 */
	public function test_should_return_alt_for_wp_user() {
		$user_id = $this->factory->user->create( [ 'display_name' => 'Jane Doe' ] );
		$user    = get_user_by( 'id', $user_id );

		$alt = get_avatar_alt( $user );

		$this->assertStringContainsString( 'Jane Doe', $alt, 'Alt should include display name.' );
	}

	/**
	 * Test should return default alt for non-existent user ID.
	 */
	public function test_should_return_default_for_unknown_id() {
		$alt = get_avatar_alt( 99999 );

		$this->assertSame( 'profile picture for user', $alt, 'Unknown user should get default alt.' );
	}

	/**
	 * Test should return default alt for non-existent email.
	 */
	public function test_should_return_default_for_unknown_email() {
		$alt = get_avatar_alt( 'nonexistent@example.com' );

		$this->assertSame( 'profile picture for user', $alt );
	}
}
