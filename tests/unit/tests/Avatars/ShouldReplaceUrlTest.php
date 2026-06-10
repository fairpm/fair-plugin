<?php
/**
 * Tests for FAIR\Avatars\should_replace_url().
 *
 * @package FAIR
 */

use function FAIR\Avatars\should_replace_url;
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
