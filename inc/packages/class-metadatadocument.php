<?php

namespace FAIR\Packages;

use stdClass;
use WP_Error;

class MetadataDocument {
	/**
	 * @var string
	 */
	public $id;

	/**
	 * @var string
	 */
	public $type;

	/**
	 * @var string
	 */
	public $name;

	/**
	 * @var string
	 */
	public $slug;

	/**
	 * @var string
	 */
	public $license;

	/**
	 * @var string
	 */
	public $description;

	/**
	 * @var string[]
	 */
	public $keywords = [];

	/**
	 * @var string[]
	 */
	public $authors = [];

	/**
	 * @var string[]
	 */
	public $security = [];

	/**
	 * @var string[]
	 */
	public $sections = [];

	/**
	 * @var array
	 */
	public $releases = [];

	/**
	 * Response headers from the request.
	 *
	 * @var array
	 */
	public $_headers = [];

	/**
	 * @param stdClass $data Data to parse.
	 * @return static|WP_Error Instance if valid, WP_Error otherwise.
	 */
	public static function from_data( stdClass $data ) {
		$doc = new static();
		$mandatory = [
			'id',
			'type',
			'license',
			'authors',
			'security',
		];
		foreach ( $mandatory as $key ) {
			if ( ! isset( $data->{$key} ) ) {
				return new WP_Error( 'fair.packages.metadata_document.missing_field', sprintf( __( 'Missing mandatory field: %s', 'fair' ), $key ) );
			}

			$doc->{$key} = $data->{$key};
		}

		$optional = [
			'name',
			'slug',
			'description',
			'keywords',
			'sections',
		];
		foreach ( $optional as $key ) {
			if ( isset( $data->{$key} ) ) {
				$doc->{$key} = $data->{$key};
			}
		}

		// Parse releases.
		if ( empty( $data->releases ) ) {
			return new WP_Error( 'fair.packages.metadata_document.missing_releases', __( 'No releases found in the metadata document.', 'fair' ) );
		}
		foreach ( $data->releases as $release ) {
			$release_doc = ReleaseDocument::from_data( $release );
			if ( is_wp_error( $release_doc ) ) {
				return $release_doc;
			}
			$doc->releases[] = $release_doc;
		}

		return $doc;
	}

	/**
	 * @return static|WP_Error Instance if valid, WP_Error otherwise.
	 */
	public static function from_response( array $response ) {
		$data = json_decode( $response['body'] );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error( 'fair.packages.fetch_repository.invalid_json', __( 'Could not decode repository response.', 'fair' ) );
		}

		$doc = static::from_data( $data );
		if ( is_wp_error( $doc ) ){
			return $doc;
		}

		// Pull the cache data as well.
		$headers = $response['headers'];
		$doc->_headers = $response['headers'];

		return $doc;
	}
}
