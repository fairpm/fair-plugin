<?php
/**
 * Tests for FAIR\Packages\ReleaseDocument.
 *
 * @package FAIR
 */

use FAIR\Packages\ReleaseDocument;

/**
 * Tests for FAIR\Packages\ReleaseDocument.
 *
 * @covers FAIR\Packages\ReleaseDocument::from_data
 */
class ReleaseDocumentTest extends WP_UnitTestCase {

	/**
	 * Test should normalize singleton artifact objects to arrays.
	 */
	public function test_should_normalize_singleton_artifact_objects_to_arrays() {
		$package_artifact = (object) [
			'url' => 'https://example.com/plugin.zip',
		];
		$icon_artifact = (object) [
			'url' => 'https://example.com/icon.png',
		];
		$custom_artifact = (object) [
			'url' => 'https://example.com/extra.json',
		];

		$release = ReleaseDocument::from_data(
			(object) [
				'version' => '1.2.3',
				'artifacts' => (object) [
					'package' => $package_artifact,
					'icon' => $icon_artifact,
					'x-extra' => $custom_artifact,
				],
			]
		);

		$this->assertNotWPError( $release, 'Expected a valid release document.' );
		$this->assertIsArray( $release->artifacts->package, 'Package artifacts should be normalized to an array.' );
		$this->assertIsArray( $release->artifacts->icon, 'Icon artifacts should be normalized to an array.' );
		$this->assertIsArray( $release->artifacts->{'x-extra'}, 'Custom artifact types should be normalized to an array.' );
		$this->assertSame( $package_artifact, $release->artifacts->package[0], 'The original package artifact should be preserved.' );
		$this->assertSame( $icon_artifact, $release->artifacts->icon[0], 'The original icon artifact should be preserved.' );
		$this->assertSame( $custom_artifact, $release->artifacts->{'x-extra'}[0], 'The original custom artifact should be preserved.' );
	}

	/**
	 * Test should preserve artifact arrays.
	 */
	public function test_should_preserve_artifact_arrays() {
		$package_artifact = (object) [
			'url' => 'https://example.com/plugin.zip',
		];
		$banner_artifact = (object) [
			'url' => 'https://example.com/banner.png',
		];

		$release = ReleaseDocument::from_data(
			(object) [
				'version' => '1.2.3',
				'artifacts' => (object) [
					'package' => [ $package_artifact ],
					'banner' => [ $banner_artifact ],
				],
			]
		);

		$this->assertNotWPError( $release, 'Expected a valid release document.' );
		$this->assertCount( 1, $release->artifacts->package, 'Package artifact arrays should be preserved.' );
		$this->assertCount( 1, $release->artifacts->banner, 'Banner artifact arrays should be preserved.' );
		$this->assertSame( $package_artifact, $release->artifacts->package[0], 'Existing package array entries should be preserved.' );
		$this->assertSame( $banner_artifact, $release->artifacts->banner[0], 'Existing banner array entries should be preserved.' );
	}
}
