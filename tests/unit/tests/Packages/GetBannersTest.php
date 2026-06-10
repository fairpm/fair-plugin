<?php
/**
 * Tests for FAIR\Packages\get_banners().
 *
 * @package FAIR
 */

use function FAIR\Packages\get_banners;

/**
 * Tests for FAIR\Packages\get_banners().
 *
 * @covers FAIR\Packages\get_banners
 */
class GetBannersTest extends WP_UnitTestCase {

	/**
	 * Create a banner object.
	 */
	private function banner( int $width, int $height, string $url = '' ): stdClass {
		return (object) [
			'url'    => $url ?: "https://example.com/banner-{$width}x{$height}.png",
			'width'  => $width,
			'height' => $height,
		];
	}

	/**
	 * Test should return low and high for standard banner sizes.
	 */
	public function test_should_return_low_and_high() {
		$banners = [
			$this->banner( 772, 250, 'https://example.com/banner-low.png' ),
			$this->banner( 1544, 500, 'https://example.com/banner-high.png' ),
		];

		$actual = get_banners( $banners );

		$this->assertSame( 'https://example.com/banner-low.png', $actual['low'], '772x250 should be low.' );
		$this->assertSame( 'https://example.com/banner-high.png', $actual['high'], '1544x500 should be high.' );
	}

	/**
	 * Test should return empty array for empty banners.
	 */
	public function test_should_return_empty_array_for_empty_banners() {
		$actual = get_banners( [] );

		$this->assertEmpty( $actual, 'Empty banners should return empty array.' );
	}

	/**
	 * Test should return empty array when no valid sizes are found.
	 */
	public function test_should_return_empty_array_when_no_valid_sizes() {
		$banners = [
			$this->banner( 100, 100 ),
			$this->banner( 300, 100 ),
		];

		$actual = get_banners( $banners );

		$this->assertEmpty( $actual, 'No matching sizes should return empty array.' );
	}

	/**
	 * Test should return only low when no high banner is present.
	 */
	public function test_should_return_only_low_when_no_high() {
		$banners = [ $this->banner( 772, 250 ) ];

		$actual = get_banners( $banners );

		$this->assertArrayHasKey( 'low', $actual, 'low should be present.' );
		$this->assertEmpty( $actual['high'], 'high should be empty string.' );
	}

	/**
	 * Test should return only high when no low banner is present.
	 */
	public function test_should_return_only_high_when_no_low() {
		$banners = [ $this->banner( 1544, 500 ) ];

		$actual = get_banners( $banners );

		$this->assertArrayHasKey( 'high', $actual, 'high should be present.' );
		$this->assertEmpty( $actual['low'], 'low should be empty string.' );
	}
}
