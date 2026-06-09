<?php
/**
 * Tests for FAIR\Packages\get_hashed_filename().
 *
 * @package FAIR
 */

use FAIR\Packages\MetadataDocument;
use FAIR\Tests\Factory\MetadataDocumentFactory;
use function FAIR\Packages\get_hashed_filename;

/**
 * Tests for FAIR\Packages\get_hashed_filename().
 *
 * @covers FAIR\Packages\get_hashed_filename
 */
class GetHashedFilenameTest extends WP_UnitTestCase {

	/**
	 * Test should append DID hash to plugin slug in path.
	 */
	public function test_should_append_did_hash_to_plugin_slug() {
		$metadata = MetadataDocumentFactory::builder()
			->set( 'type', 'wp-plugin' )
			->set( 'slug', 'my-plugin' )
			->set( 'filename', 'my-plugin/my-plugin.php' )
			->build();

		$actual = get_hashed_filename( $metadata );

		// Expected: my-plugin-<6-char-hash>/my-plugin.php
		$this->assertMatchesRegularExpression(
			'#^my-plugin-[a-f0-9]{6}/my-plugin\.php$#',
			$actual,
			'Plugin filename should have DID hash appended to slug directory.'
		);
	}

	/**
	 * Test should append DID hash to theme slug.
	 */
	public function test_should_append_did_hash_to_theme_slug() {
		$metadata = MetadataDocumentFactory::builder()
			->set( 'type', 'wp-theme' )
			->set( 'slug', 'my-theme' )
			->set( 'filename', 'my-theme/style.css' )
			->build();

		$actual = get_hashed_filename( $metadata );

		// Theme: just slug-didhash (no subpath)
		$this->assertMatchesRegularExpression(
			'#^my-theme-[a-f0-9]{6}$#',
			$actual,
			'Theme filename should be slug-didhash without subpath.'
		);
	}

	/**
	 * Test should not double-append hash if slug already contains it.
	 */
	public function test_should_not_double_append_hash() {
		$metadata = MetadataDocumentFactory::builder()
			->set( 'type', 'wp-plugin' )
			->set( 'slug', 'my-plugin' )
			->set( 'filename', 'my-plugin/my-plugin.php' )
			->build();

		// First call to get the hash
		$hashed = get_hashed_filename( $metadata );

		// Now set the slug to include the hash
		$hash = substr( $hashed, strlen( 'my-plugin-' ), 6 );
		$metadata_with_hash = MetadataDocumentFactory::builder()
			->set( 'type', 'wp-plugin' )
			->set( 'slug', "my-plugin-{$hash}" )
			->set( 'filename', "my-plugin-{$hash}/my-plugin.php" )
			->build();

		$actual = get_hashed_filename( $metadata_with_hash );

		// Should not become my-plugin-xxxxxx-xxxxxx
		$this->assertStringNotContainsString(
			"{$hash}-{$hash}",
			$actual,
			'Hash should not be appended twice.'
		);
	}

	/**
	 * Test should produce deterministic output.
	 */
	public function test_should_be_deterministic() {
		$metadata = MetadataDocumentFactory::full();

		$this->assertSame(
			get_hashed_filename( $metadata ),
			get_hashed_filename( $metadata ),
			'Same metadata should produce same filename.'
		);
	}

	/**
	 * Test should produce different filenames for different DIDs.
	 */
	public function test_should_differ_for_different_dids() {
		$meta1 = MetadataDocumentFactory::builder()
			->set( 'type', 'wp-plugin' )
			->set( 'slug', 'test-plugin' )
			->set( 'filename', 'test-plugin/test-plugin.php' )
			->set( 'id', 'did:plc:z72i7hdynmk6r22z27h6tvur' )
			->build();

		$meta2 = MetadataDocumentFactory::builder()
			->set( 'type', 'wp-plugin' )
			->set( 'slug', 'test-plugin' )
			->set( 'filename', 'test-plugin/test-plugin.php' )
			->set( 'id', 'did:plc:ppicmk23c5pimdivve34bcp2' )
			->build();

		$this->assertNotSame(
			get_hashed_filename( $meta1 ),
			get_hashed_filename( $meta2 ),
			'Different DIDs with same slug should produce different filenames.'
		);
	}
}
