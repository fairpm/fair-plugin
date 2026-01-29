<?php
/**
 * Package data container.
 *
 * @package FAIR
 */

namespace FAIR\Updater;

use FAIR\Packages;

/**
 * Represents a registered FAIR package (plugin or theme).
 */
abstract class Package {

	/**
	 * The DID of the package.
	 *
	 * @var string
	 */
	public string $did;

	/**
	 * Absolute path to the main file.
	 *
	 * @var string
	 */
	public string $filepath;

	/**
	 * Current installed version.
	 *
	 * @var string|null
	 */
	public ?string $local_version;

	/**
	 * Cached metadata document.
	 *
	 * @var \FAIR\Packages\MetadataDocument|null
	 */
	private $metadata = null;

	/**
	 * Cached release document.
	 *
	 * @var \FAIR\Packages\ReleaseDocument|null
	 */
	private $release = null;

	/**
	 * Constructor.
	 *
	 * @param string $did      The DID of the package.
	 * @param string $filepath Absolute path to the main file.
	 */
	public function __construct( string $did, string $filepath ) {
		$this->did           = $did;
		$this->filepath      = $filepath;
		$this->local_version = $filepath ? get_file_data( $filepath, [ 'Version' => 'Version' ] )['Version'] : null;
	}

	/**
	 * Get the package slug.
	 *
	 * @return string The slug (directory name for plugins, stylesheet for themes).
	 */
	abstract public function get_slug(): string;

	/**
	 * Get the relative path used in update transients.
	 *
	 * @return string The relative path.
	 */
	abstract public function get_relative_path(): string;

	/**
	 * Get the metadata document, fetching and caching if needed.
	 *
	 * @return \FAIR\Packages\MetadataDocument|\WP_Error|null
	 */
	final public function get_metadata() {
		if ( $this->metadata === null ) {
			$metadata = Packages\fetch_package_metadata( $this->did );
			if ( ! is_wp_error( $metadata ) ) {
				$this->metadata = $metadata;
			}
			return $metadata;
		}
		return $this->metadata;
	}

	/**
	 * Get the release document, fetching and caching if needed.
	 *
	 * @return \FAIR\Packages\ReleaseDocument|\WP_Error|null
	 */
	final public function get_release() {
		if ( $this->release === null ) {
			$release = Packages\get_latest_release_from_did( $this->did );
			if ( ! is_wp_error( $release ) ) {
				$this->release = $release;
			}
			return $release;
		}
		return $this->release;
	}
}
