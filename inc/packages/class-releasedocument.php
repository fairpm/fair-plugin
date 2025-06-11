<?php

namespace FAIR\Packages;

use stdClass;
use WP_Error;

class ReleaseDocument {
	/**
	 * @var string
	 */
	public $version;

	/**
	 * @var stdClass
	 */
	public $artifacts;

	/**
	 * @var array
	 */
	public $provides;

	/**
	 * @var array
	 */
	public $requires;

	/**
	 * @var array
	 */
	public $suggests;

	/**
	 * @var array
	 */
	public $auth;

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
