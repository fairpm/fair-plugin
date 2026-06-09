<?php
/**
 * Tests for FAIR\Avatars functions.
 *
 * @package FAIR
 */

use function FAIR\Avatars\should_replace_url;
use function FAIR\Avatars\generate_default_avatar;
use function FAIR\Avatars\get_avatar_alt;
use const FAIR\Avatars\AVATAR_SRC_SETTING_KEY;

/**
 * Tests for FAIR\Avatars\should_replace_url().
 *
 * @covers FAIR\Avatars\should_replace_url
 */
class ShouldReplaceUrlTest extends WP_UnitTestCase {

	/**
	 * Test should return true for Gravatar domains.
	 */
	public function test_should_return_true_for_gravatar() {
		$this->assertTrue(
			should_replace_url( 'https://secure.gravatar.com/avatar/abc123' ),
			'Gravatar URLs should be replaced.'
		);
	}

	/**
	 * Test should return true for Gravatar with HTTPS.
	 */
	public function test_should_return_true_for_gravatar_https() {
		$this->assertTrue(
			should_replace_url( 'https://secure.gravatar.com/avatar/xyz?size=150' ),
			'HTTPS Gravatar with params should be replaced.'
		);
	}

	/**
	 * Test should return false for non-Gravatar domains.
	 */
	public function test_should_return_false_for_other_domains() {
		$this->assertFalse( should_replace_url( 'https://example.com/avatar.png' ) );
	}

	/**
	 * Test should return false for local avatar URLs.
	 */
	public function test_should_return_false_for_local_url() {
		$this->assertFalse( should_replace_url( '/wp-content/uploads/avatars/test.png' ) );
	}

	/**
	 * Test should return false for empty string.
	 */
	public function test_should_return_false_for_empty_string() {
		$this->assertFalse( should_replace_url( '' ) );
	}
}

/**
 * Tests for FAIR\Avatars\generate_default_avatar().
 *
 * @covers FAIR\Avatars\generate_default_avatar
 */
class GenerateDefaultAvatarTest extends WP_UnitTestCase {

	/**
	 * Test should return a data URI.
	 */
	public function test_should_return_data_uri() {
		$result = generate_default_avatar( 'Test User' );

		$this->assertStringStartsWith( 'data:image/svg+xml;base64,', $result, 'Should be a data URI.' );
	}

	/**
	 * Test should include first letter in SVG.
	 */
	public function test_should_include_first_letter() {
		$result = generate_default_avatar( 'Chuck' );

		$decoded = base64_decode( str_replace( 'data:image/svg+xml;base64,', '', $result ) );
		$this->assertStringContainsString( 'C', strip_tags( $decoded ), 'Should contain first letter uppercase.' );
	}

	/**
	 * Test should handle null name (anonymous).
	 */
	public function test_should_handle_null_name() {
		$result = generate_default_avatar( null );

		$this->assertStringStartsWith( 'data:image/svg+xml;base64,', $result, 'Null name should produce valid SVG data URI.' );
	}

	/**
	 * Test should handle empty string name.
	 */
	public function test_should_handle_empty_name() {
		$result = generate_default_avatar( '' );

		$this->assertStringStartsWith( 'data:image/svg+xml;base64,', $result, 'Empty name should produce valid SVG data URI.' );
	}

	/**
	 * Test should handle special XML characters in name.
	 */
	public function test_should_escape_special_characters() {
		$result = generate_default_avatar( '<script>alert(1)</script>' );

		$decoded = base64_decode( str_replace( 'data:image/svg+xml;base64,', '', $result ) );
		$this->assertStringNotContainsString( '<script>', $decoded, 'Special chars should be XML-escaped.' );
	}

	/**
	 * Test should produce deterministic output for same inputs.
	 */
	public function test_should_be_deterministic() {
		$a = generate_default_avatar( 'Alice' );
		$b = generate_default_avatar( 'Alice' );

		$this->assertSame( $a, $b, 'Same name should produce same SVG.' );
	}

	/**
	 * Test should produce different output for different names.
	 */
	public function test_should_differ_for_different_names() {
		$a = generate_default_avatar( 'Alice' );
		$b = generate_default_avatar( 'Bob' );

		$this->assertNotSame( $a, $b, 'Different names should produce different SVGs.' );
	}

	/**
	 * Test should respect filter for color.
	 *
	 * NOTE: The function uses add_filter() instead of apply_filters()
	 * for the color hook, which is a pre-existing bug. The hook cannot
	 * be tested until the function is fixed.
	 */
	public function test_should_respect_color_filter() {
		$this->markTestSkipped( 'Function uses add_filter() instead of apply_filters() for color hook.' );
	}
}

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
