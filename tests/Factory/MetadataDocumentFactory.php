<?php
/**
 * Test factory for MetadataDocument objects.
 *
 * @package FAIR
 */

namespace FAIR\Tests\Factory;

use FAIR\Packages\MetadataDocument;
use FAIR\Packages\ReleaseDocument;

/**
 * Creates MetadataDocument instances for testing.
 *
 * All factory methods produce valid documents. Use the builder pattern
 * for invalid or edge-case documents.
 */
class MetadataDocumentFactory {

	/**
	 * Create a fully-populated MetadataDocument with icon, banner, and multiple releases.
	 */
	public static function full(): MetadataDocument {
		return self::from_fixture( 'metadata-doc-full' );
	}

	/**
	 * Create a minimal valid MetadataDocument (mandatory fields only plus one release).
	 */
	public static function minimal(): MetadataDocument {
		return self::from_fixture( 'metadata-doc-minimal' );
	}

	/**
	 * Create a MetadataDocument from a named fixture file.
	 *
	 * @param string $name Fixture name without path or extension (e.g., 'metadata-doc-full').
	 */
	public static function from_fixture( string $name ): MetadataDocument {
		$path  = dirname( __DIR__, 2 ) . '/tests/fixtures/' . $name . '.json';
		$json  = file_get_contents( $path );
		$data  = json_decode( $json );

		return MetadataDocument::from_data( $data );
	}

	/**
	 * Create a MetadataDocument from raw JSON.
	 */
	public static function from_json( string $json ): MetadataDocument {
		return MetadataDocument::from_data( json_decode( $json ) );
	}

	/**
	 * Create a MetadataDocument with a specific ID.
	 */
	public static function with_id( string $id ): MetadataDocument {
		return self::builder()
			->set( 'id', $id )
			->build();
	}

	/**
	 * Create a MetadataDocument without a releases array (invalid).
	 *
	 * Returns raw data that will trigger the 'missing_releases' error
	 * when passed to MetadataDocument::from_data().
	 */
	public static function without_releases(): \stdClass {
		$data        = json_decode( file_get_contents( self::fixture_path( 'metadata-doc-minimal' ) ) );
		unset( $data->releases );
		return $data;
	}

	/**
	 * Create a MetadataDocument without a mandatory field.
	 *
	 * Returns raw data that will trigger a 'missing_field' error.
	 *
	 * @param string $field The mandatory field to omit ('id', 'type', 'license', 'authors', 'security').
	 */
	public static function without_field( string $field ): \stdClass {
		$data = json_decode( file_get_contents( self::fixture_path( 'metadata-doc-minimal' ) ) );
		unset( $data->{$field} );
		return $data;
	}

	/**
	 * Create a builder for constructing MetadataDocument instances with custom overrides.
	 */
	public static function builder(): MetadataDocumentBuilder {
		return new MetadataDocumentBuilder();
	}

	/**
	 * Get the path to a fixture file.
	 */
	private static function fixture_path( string $name ): string {
		return dirname( __DIR__, 2 ) . '/tests/fixtures/' . $name . '.json';
	}

	/**
	 * Get raw data from a fixture as a stdClass.
	 */
	public static function raw_data( string $name ): \stdClass {
		return json_decode( file_get_contents( self::fixture_path( $name ) ) );
	}
}

/**
 * Builder for constructing MetadataDocument instances with field overrides.
 *
 * The builder starts from the 'minimal' fixture and applies overrides.
 */
class MetadataDocumentBuilder {

	/** @var \stdClass */
	private \stdClass $data;

	public function __construct() {
		$this->data = json_decode( file_get_contents(
			dirname( __DIR__, 2 ) . '/tests/fixtures/metadata-doc-minimal.json'
		) );
	}

	/**
	 * Set a field on the underlying data.
	 *
	 * @return $this
	 */
	public function set( string $key, $value ): self {
		$this->data->{$key} = $value;
		return $this;
	}

	/**
	 * Unset a field on the underlying data.
	 *
	 * @return $this
	 */
	public function unset( string $key ): self {
		unset( $this->data->{$key} );
		return $this;
	}

	/**
	 * Set the releases array from raw release data.
	 *
	 * @param \stdClass[] $releases
	 * @return $this
	 */
	public function with_releases( array $releases ): self {
		$this->data->releases = $releases;
		return $this;
	}

	/**
	 * Set the type of the package (e.g., 'wp-plugin', 'wp-theme').
	 *
	 * @return $this
	 */
	public function with_type( string $type ): self {
		$this->data->type = $type;
		return $this;
	}

	/**
	 * Set the authors array.
	 *
	 * @param \stdClass[] $authors
	 * @return $this
	 */
	public function with_authors( array $authors ): self {
		$this->data->authors = $authors;
		return $this;
	}

	/**
	 * Build a MetadataDocument from the current builder state.
	 */
	public function build(): MetadataDocument {
		return MetadataDocument::from_data( $this->data );
	}

	/**
	 * Get the raw data without building.
	 */
	public function raw(): \stdClass {
		return $this->data;
	}
}
