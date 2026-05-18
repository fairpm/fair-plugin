<?php
/**
 * Tests for FAIR\Packages package identity helpers.
 *
 * @package FAIR
 */

use FAIR\Packages\MetadataDocument;
use function FAIR\Packages\get_did_hash;
use function FAIR\Packages\get_hashed_slug;

/**
 * Tests for FAIR\Packages package identity helpers.
 *
 * @covers FAIR\Packages\get_hashed_slug
 */
class GetHashedPackageIdentityTest extends WP_UnitTestCase {

	/**
	 * Test that hashed slugs stay decoupled from the plugin bootstrap filename.
	 */
	public function test_should_return_hashed_slug_for_plugin_metadata() {
		$metadata = $this->create_metadata_document(
			[
				'filename' => 'example/example.php',
				'slug' => 'example',
				'type' => 'wp-plugin',
			]
		);

		$this->assertSame( 'example-' . get_did_hash( $metadata->id ), get_hashed_slug( $metadata ) );
	}

	/**
	 * Test that plugin slugs hash independently of the bootstrap filename.
	 */
	public function test_should_ignore_plugin_bootstrap_filename_when_hashing_slug() {
		$metadata = $this->create_metadata_document(
			[
				'filename' => 'example/custom-bootstrap.php',
				'slug' => 'example',
				'type' => 'wp-plugin',
			]
		);

		$this->assertSame( 'example-' . get_did_hash( $metadata->id ), get_hashed_slug( $metadata ) );
	}

	/**
	 * Test that malformed plugin filenames still produce a valid hashed slug.
	 */
	public function test_should_recover_when_plugin_filename_has_no_main_file() {
		$metadata = $this->create_metadata_document(
			[
				'filename' => 'example',
				'slug' => 'example',
				'type' => 'wp-plugin',
			]
		);

		$this->assertSame( 'example-' . get_did_hash( $metadata->id ), get_hashed_slug( $metadata ) );
	}

	/**
	 * Test that theme slugs append the DID hash.
	 */
	public function test_should_hash_theme_slug() {
		$metadata = $this->create_metadata_document(
			[
				'filename' => 'example',
				'slug' => 'example',
				'type' => 'wp-theme',
			]
		);

		$this->assertSame( 'example-' . get_did_hash( $metadata->id ), get_hashed_slug( $metadata ) );
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

		$this->assertSame( 'example-' . get_did_hash( $metadata->id ), get_hashed_slug( $metadata ) );
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

		$this->assertSame( 'example-' . $hash, get_hashed_slug( $metadata ) );
	}

	/**
	 * Test that a hash-like substring in the middle of the slug still gets the DID hash appended.
	 */
	public function test_should_append_hash_when_same_value_appears_in_middle_of_plugin_slug() {
		$hash = get_did_hash( 'did:plc:example1234567890123456789' );
		$slug = 'vendor-' . $hash . '-plugin';
		$metadata = $this->create_metadata_document(
			[
				'filename' => $slug . '/' . $slug . '.php',
				'slug' => $slug,
				'type' => 'wp-plugin',
			]
		);

		$this->assertSame( $slug . '-' . $hash, get_hashed_slug( $metadata ) );
	}

	/**
	 * Test that empty plugin filenames behave the same as missing filenames for slug hashing.
	 */
	public function test_should_fall_back_to_slug_when_plugin_filename_is_empty_string() {
		$metadata = $this->create_metadata_document(
			[
				'filename' => '',
				'slug' => 'example',
				'type' => 'wp-plugin',
			]
		);

		$this->assertSame( 'example-' . get_did_hash( $metadata->id ), get_hashed_slug( $metadata ) );
	}

	/**
	 * Test that a missing type is treated as non-plugin metadata.
	 *
	 * This covers the operator precedence difference between
	 * `'wp-plugin' === ( $metadata->type ?? '' )` and
	 * `'wp-plugin' === $metadata->type ?? ''`.
	 */
	public function test_should_treat_missing_type_as_non_plugin_metadata() {
		$metadata = (object) [
			'id' => 'did:plc:example1234567890123456789',
			'slug' => 'example',
			'filename' => 'example/example.php',
		];

		$this->assertSame( 'example-' . get_did_hash( $metadata->id ), get_hashed_slug( $metadata ) );
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
