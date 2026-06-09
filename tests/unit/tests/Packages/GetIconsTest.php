<?php
/**
 * Tests for FAIR\Packages\get_icons().
 *
 * @package FAIR
 */

use function FAIR\Packages\get_icons;

/**
 * Tests for FAIR\Packages\get_icons().
 *
 * @covers FAIR\Packages\get_icons
 */
class GetIconsTest extends WP_UnitTestCase {

	/**
	 * Create an icon object.
	 */
	private function icon( int $width, int $height, string $url = '', string $content_type = 'image/png' ): stdClass {
		return (object) [
			'url'          => $url ?: "https://example.com/icon-{$width}x{$height}.png",
			'width'        => $width,
			'height'       => $height,
			'content-type' => $content_type,
		];
	}

	/**
	 * Test should return 1x and 2x for standard icon sizes.
	 */
	public function test_should_return_1x_and_2x() {
		$icons = [
			$this->icon( 128, 128, 'https://example.com/icon-128.png' ),
			$this->icon( 256, 256, 'https://example.com/icon-256.png' ),
		];

		$actual = get_icons( $icons );

		$this->assertSame( 'https://example.com/icon-128.png', $actual['1x'], '128x128 should be 1x.' );
		$this->assertSame( 'https://example.com/icon-256.png', $actual['2x'], '256x256 should be 2x.' );
	}

	/**
	 * Test should return SVG as 'default' when URL is from s.w.org/plugins.
	 */
	public function test_should_return_svg_as_default_for_wporg_svg() {
		$icons = [
			$this->icon( 128, 128 ),
			$this->icon( 256, 256 ),
			(object) [
				'url'          => 'https://s.w.org/plugins/geopattern-icon/test.svg',
				'width'        => 128,
				'height'       => 128,
				'content-type' => 'image/svg+xml',
			],
		];

		$actual = get_icons( $icons );

		$this->assertArrayHasKey( 'default', $actual, 'SVG from s.w.org should be under default key.' );
		$this->assertStringContainsString( 's.w.org', $actual['default'], 'Default should be the s.w.org SVG URL.' );
	}

	/**
	 * Test should return non-WP.org SVG as 'svg' key.
	 */
	public function test_should_return_non_wporg_svg_as_svg_key() {
		$icons = [
			$this->icon( 128, 128 ),
			(object) [
				'url'          => 'https://fair.pm/icons/icon.svg',
				'width'        => 128,
				'height'       => 128,
				'content-type' => 'image/svg+xml',
			],
		];

		$actual = get_icons( $icons );

		$this->assertArrayHasKey( 'svg', $actual, 'Non-wporg SVG should be under svg key.' );
		$this->assertSame( 'https://fair.pm/icons/icon.svg', $actual['svg'], 'SVG URL should match.' );
	}

	/**
	 * Test should return empty array for empty icons.
	 */
	public function test_should_return_empty_array_for_empty_icons() {
		$actual = get_icons( [] );

		$this->assertEmpty( $actual, 'Empty icons should return empty array.' );
	}

	/**
	 * Test should return empty array when no valid sizes are found.
	 */
	public function test_should_return_empty_array_when_no_valid_sizes() {
		$icons = [
			$this->icon( 64, 64 ),
			$this->icon( 512, 512 ),
		];

		$actual = get_icons( $icons );

		$this->assertEmpty( $actual, 'No matching sizes should return empty array.' );
	}

	/**
	 * Test should return only 1x when no 2x icon is present.
	 */
	public function test_should_return_only_1x_when_no_2x() {
		$icons = [ $this->icon( 128, 128 ) ];

		$actual = get_icons( $icons );

		$this->assertArrayHasKey( '1x', $actual, '1x should be present.' );
		$this->assertEmpty( $actual['2x'], '2x should be empty string when no 256 icon.' );
	}

	/**
	 * Test should return only 2x when no 1x icon is present.
	 */
	public function test_should_return_only_2x_when_no_1x() {
		$icons = [ $this->icon( 256, 256 ) ];

		$actual = get_icons( $icons );

		$this->assertArrayHasKey( '2x', $actual, '2x should be present.' );
		$this->assertEmpty( $actual['1x'], '1x should be empty string when no 128 icon.' );
	}
}
