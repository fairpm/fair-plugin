<?php
/**
 * Create MetadataDocument.
 *
 * @package FAIR
 */

namespace FAIR\Packages;

use stdClass;
use WP_Error;

/**
 * Class MetadataDocument.
 */
class MetadataDocument {
	/**
	 * DID.
	 *
	 * @var string
	 */
	public $id;

	/**
	 * Document type.
	 *
	 * @var string
	 */
	public $type;

	/**
	 * Name.
	 *
	 * @var string
	 */
	public $name;

	/**
	 * Slug.
	 *
	 * @var string
	 */
	public $slug;

	/**
	 * File name.
	 *
	 * @var string
	 */
	public $filename;

	/**
	 * License.
	 *
	 * @var string
	 */
	public $license;

	/**
	 * Description.
	 *
	 * @var string
	 */
	public $description;

	/**
	 * Keywords.
	 *
	 * @var string[]
	 */
	public $keywords = [];

	/**
	 * Authors.
	 *
	 * @var string[]
	 */
	public $authors = [];

	/**
	 * Security.
	 *
	 * @var string[]
	 */
	public $security = [];

	/**
	 * Last updated.
	 *
	 * @var string
	 */
	public $last_updated;

	/**
	 * Sections.
	 *
	 * @var stdClass
	 */
	public $sections;

	/**
	 * Releases.
	 *
	 * @var ReleaseDocument[]
	 */
	public $releases = [];

	/**
	 * Response headers from the request.
	 *
	 * @var array
	 */
	public $_headers = [];

	/**
	 * Collate data.
	 *
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
				/* translators: %s is the missing field name. */
				return new WP_Error( 'fair.packages.metadata_document.missing_field', sprintf( __( 'Missing mandatory field: %s', 'fair' ), $key ) );
			}

			$doc->{$key} = $data->{$key};
		}

		$optional = [
			'name',
			'slug',
			'filename',
			'description',
			'keywords',
			'last_updated',
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
	 * Collate response.
	 *
	 * @param array $response Response data.
	 * @return static|WP_Error Instance if valid, WP_Error otherwise.
	 */
	public static function from_response( array $response ) {
		$data = json_decode( $response['body'] );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error( 'fair.packages.fetch_repository.invalid_json', __( 'Could not decode repository response.', 'fair' ) );
		}

		$doc = static::from_data( $data );
		if ( is_wp_error( $doc ) ) {
			return $doc;
		}

		// Pull the cache data as well.
		$headers = $response['headers'];
		$doc->_headers = $response['headers'];

		return $doc;
	}
}
