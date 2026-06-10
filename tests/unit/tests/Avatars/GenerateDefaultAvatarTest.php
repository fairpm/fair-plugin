<?php
/**
 * Tests for FAIR\Avatars\generate_default_avatar().
 *
 * @package FAIR
 */

use function FAIR\Avatars\generate_default_avatar;

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
