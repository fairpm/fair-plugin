<?php
/**
 * Create Release Document.
 *
 * @package FAIR
 */

namespace FAIR\Packages;

use stdClass;
use WP_Error;

/**
 * Class ReleaseDocument.
 */
class ReleaseDocument {
	/**
	 * Version.
	 *
	 * @var string
	 */
	public $version;

	/**
	 * Artifacts.
	 *
	 * @var stdClass
	 */
	public $artifacts;

	/**
	 * Provides.
	 *
	 * @var array
	 */
	public $provides;

	/**
	 * Requires.
	 *
	 * @var array
	 */
	public $requires;

	/**
	 * Suggests.
	 *
	 * @var array
	 */
	public $suggests;

	/**
	 * Authorization.
	 *
	 * @var array
	 */
	public $auth;

	/**
	 * Release.
	 *
	 * @var ReleaseDocument
	 */
	public $release;

	/**
	 * Collate data.
	 *
	 * @param  stdClass $data Data to parse.
	 *
	 * @return ReleaseDocument|WP_Error
	 */
	public static function from_data( stdClass $data ) {
		$doc = new static();
		$mandatory = [
			'version',
			'artifacts',
		];
		foreach ( $mandatory as $key ) {
			if ( ! isset( $data->{$key} ) ) {
				return new WP_Error( 'fair.packages.metadata_document.missing_field', sprintf( __( 'Missing mandatory field: %s', 'fair' ), $key ) );
			}
			$doc->{$key} = $data->{$key};
		}

		$optional = [
			'provides',
			'requires',
			'suggests',
			'auth',
		];
		foreach ( $optional as $key ) {
			if ( isset( $data->{$key} ) ) {
				$doc->{$key} = $data->{$key};
			}
		}

		return $doc;
	}
}
