<?php
/**
 * Tests for FAIR\Packages\get_hashed_filename().
 *
 * @package FAIR
 */

use FAIR\Packages\MetadataDocument;
use function FAIR\Packages\get_did_hash;
use function FAIR\Packages\get_hashed_filename;

/**
 * Tests for FAIR\Packages\get_hashed_filename().
 *
 * @covers FAIR\Packages\get_hashed_filename
 */
class GetHashedFilenameTest extends WP_UnitTestCase {

	/**
	 * Test that plugin filenames append the DID hash to the directory name.
	 */
	public function test_should_hash_plugin_directory_name() {
		$metadata = $this->create_metadata_document(
			[
				'filename' => 'example/example.php',
				'slug' => 'example',
				'type' => 'wp-plugin',
			]
		);

		$this->assertSame( 'example-' . get_did_hash( $metadata->id ) . '/example.php', get_hashed_filename( $metadata ) );
	}

	/**
	 * Test that missing plugin filenames fall back to the slug.
	 */
	public function test_should_fall_back_to_slug_when_plugin_filename_missing() {
		$metadata = $this->create_metadata_document(
			[
				'filename' => null,
				'slug' => 'example',
				'type' => 'wp-plugin',
			]
		);

		$this->assertSame( 'example-' . get_did_hash( $metadata->id ) . '/example.php', get_hashed_filename( $metadata ) );
	}

	/**
	 * Test that malformed plugin filenames still produce a valid hashed path.
	 */
	public function test_should_recover_when_plugin_filename_has_no_main_file() {
		$metadata = $this->create_metadata_document(
			[
				'filename' => 'example',
				'slug' => 'example',
				'type' => 'wp-plugin',
			]
		);

		$this->assertSame( 'example-' . get_did_hash( $metadata->id ) . '/example.php', get_hashed_filename( $metadata ) );
	}

	/**
	 * Test that theme filenames append the DID hash to the slug.
	 */
	public function test_should_hash_theme_slug() {
		$metadata = $this->create_metadata_document(
			[
				'filename' => 'example',
				'slug' => 'example',
				'type' => 'wp-theme',
			]
		);

		$this->assertSame( 'example-' . get_did_hash( $metadata->id ), get_hashed_filename( $metadata ) );
	}

	/**
	 * Test that missing non-plugin filenames still fall back to the slug.
	 */
	public function test_should_fall_back_to_slug_for_non_plugin_when_filename_missing() {
		$metadata = $this->create_metadata_document(
			[
				'filename' => null,
				'slug' => 'example',
				'type' => 'wp-theme',
			]
		);

		$this->assertSame( 'example-' . get_did_hash( $metadata->id ), get_hashed_filename( $metadata ) );
	}

	/**
	 * Test that a pre-hashed plugin slug is not hashed twice.
	 */
	public function test_should_not_append_hash_twice_for_plugin_slug() {
		$hash = get_did_hash( 'did:plc:example1234567890123456789' );
		$metadata = $this->create_metadata_document(
			[
				'filename' => 'example-' . $hash . '/example.php',
				'slug' => 'example-' . $hash,
				'type' => 'wp-plugin',
			]
		);

		$this->assertSame( 'example-' . $hash . '/example.php', get_hashed_filename( $metadata ) );
	}

	/**
	 * Test that empty plugin filenames behave the same as missing filenames.
	 */
	public function test_should_fall_back_to_slug_when_plugin_filename_is_empty_string() {
		$metadata = $this->create_metadata_document(
			[
				'filename' => '',
				'slug' => 'example',
				'type' => 'wp-plugin',
			]
		);

		$this->assertSame( 'example-' . get_did_hash( $metadata->id ) . '/example.php', get_hashed_filename( $metadata ) );
	}

	/**
	 * Create a metadata document for testing.
	 *
	 * @param array $overrides Document overrides.
	 * @return MetadataDocument
	 */
	private function create_metadata_document( array $overrides ) : MetadataDocument {
		$metadata = new MetadataDocument();
		$metadata->id = 'did:plc:example1234567890123456789';
		$metadata->type = 'wp-plugin';
		$metadata->slug = 'example';
		$metadata->filename = 'example/example.php';

		foreach ( $overrides as $key => $value ) {
			$metadata->{$key} = $value;
		}

		return $metadata;
	}
}
