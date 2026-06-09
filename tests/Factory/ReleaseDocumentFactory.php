<?php
/**
 * Test factory for ReleaseDocument objects.
 *
 * @package FAIR
 */

namespace FAIR\Tests\Factory;

use FAIR\Packages\ReleaseDocument;

/**
 * Creates ReleaseDocument instances for testing.
 */
class ReleaseDocumentFactory {

	/**
	 * Create a fully-populated ReleaseDocument with icons, banners, provides, requires, suggests, and auth.
	 */
	public static function full(): ReleaseDocument {
		return self::from_fixture( 'release-doc-v1.0.0' );
	}

	/**
	 * Create a ReleaseDocument with requirements populated.
	 */
	public static function with_requirements(): ReleaseDocument {
		return self::from_fixture( 'release-doc-with-requirements' );
	}

	/**
	 * Create a ReleaseDocument with a specific version.
	 */
	public static function with_version( string $version ): ReleaseDocument {
		return self::builder()
			->set( 'version', $version )
			->build();
	}

	/**
	 * Create a ReleaseDocument from a named fixture file.
	 *
	 * @param string $name Fixture name without path or extension.
	 */
	public static function from_fixture( string $name ): ReleaseDocument {
		$path = dirname( __DIR__, 2 ) . '/tests/fixtures/' . $name . '.json';
		$json = file_get_contents( $path );
		$data = json_decode( $json );

		return ReleaseDocument::from_data( $data );
	}

	/**
	 * Create a ReleaseDocument from raw JSON.
	 */
	public static function from_json( string $json ): ReleaseDocument {
		return ReleaseDocument::from_data( json_decode( $json ) );
	}

	/**
	 * Create raw data that will fail mandatory-field validation.
	 *
	 * @param string $field The mandatory field to omit ('version', 'artifacts').
	 */
	public static function without_field( string $field ): \stdClass {
		$data = json_decode( file_get_contents( self::fixture_path( 'release-doc-v1.0.0' ) ) );
		unset( $data->{$field} );
		return $data;
	}

	/**
	 * Create raw data that will fail artifacts validation (empty artifacts).
	 */
	public static function without_artifacts(): \stdClass {
		$data = json_decode( file_get_contents( self::fixture_path( 'release-doc-no-artifacts' ) ) );
		return $data;
	}

	/**
	 * Create a builder for constructing ReleaseDocument instances with custom overrides.
	 */
	public static function builder(): ReleaseDocumentBuilder {
		return new ReleaseDocumentBuilder();
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

	/**
	 * Create an array of ReleaseDocument instances sorted by version (newest first).
	 *
	 * @param string ...$versions Version strings in any order.
	 * @return ReleaseDocument[]
	 */
	public static function list_of( string ...$versions ): array {
		return array_map(
			fn( string $v ) => self::with_version( $v ),
			$versions
		);
	}
}

/**
 * Builder for constructing ReleaseDocument instances with field overrides.
 */
class ReleaseDocumentBuilder {

	/** @var \stdClass */
	private \stdClass $data;

	public function __construct() {
		$this->data = json_decode( file_get_contents(
			dirname( __DIR__, 2 ) . '/tests/fixtures/release-doc-v1.0.0.json'
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
	 * Unset a field.
	 *
	 * @return $this
	 */
	public function unset( string $key ): self {
		unset( $this->data->{$key} );
		return $this;
	}

	/**
	 * Set the version.
	 *
	 * @return $this
	 */
	public function with_version( string $version ): self {
		$this->data->version = $version;
		return $this;
	}

	/**
	 * Set the requires array.
	 *
	 * @return $this
	 */
	public function with_requires( array $requires ): self {
		$this->data->requires = (object) $requires;
		return $this;
	}

	/**
	 * Set the suggests array.
	 *
	 * @return $this
	 */
	public function with_suggests( array $suggests ): self {
		$this->data->suggests = (object) $suggests;
		return $this;
	}

	/**
	 * Set the artifacts.
	 *
	 * @return $this
	 */
	public function with_artifacts( \stdClass $artifacts ): self {
		$this->data->artifacts = $artifacts;
		return $this;
	}

	/**
	 * Set the package artifacts (download URLs).
	 *
	 * @param \stdClass[] $packages Array of package objects with url, lang, content-type, signature keys.
	 * @return $this
	 */
	public function with_packages( array $packages ): self {
		if ( ! isset( $this->data->artifacts ) ) {
			$this->data->artifacts = new \stdClass();
		}
		$this->data->artifacts->package = $packages;
		return $this;
	}

	/**
	 * Set icon artifacts.
	 *
	 * @param \stdClass[] $icons
	 * @return $this
	 */
	public function with_icons( array $icons ): self {
		if ( ! isset( $this->data->artifacts ) ) {
			$this->data->artifacts = new \stdClass();
		}
		$this->data->artifacts->icon = $icons;
		return $this;
	}

	/**
	 * Build a ReleaseDocument from the current builder state.
	 */
	public function build(): ReleaseDocument {
		return ReleaseDocument::from_data( $this->data );
	}

	/**
	 * Get the raw data without building.
	 */
	public function raw(): \stdClass {
		return $this->data;
	}
}
