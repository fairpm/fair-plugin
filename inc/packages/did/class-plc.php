<?php
/**
 * Get PLC document.
 *
 * @package FAIR
 */

namespace FAIR\Packages\DID;

use WP_Error;

/**
 * Class PLC
 */
class PLC implements DID {
	// phpcs:disable WordPress.NamingConventions.ValidVariableName

	const DIRECTORY_URL = 'https://plc.directory/';
	const TYPE = 'plc';

	/**
	 * Decentralized ID.
	 *
	 * @var string
	 */
	protected string $id;

	/**
	 * Constructor.
	 *
	 * @param string $id DID.
	 */
	public function __construct( string $id ) {
		$this->id = $id;
	}

	/**
	 * Get the DID type.
	 *
	 * One of plc, web.
	 */
	public function get_type() : string {
		return static::TYPE;
	}

	/**
	 * Get the full decentralized ID (DID).
	 */
	public function get_id() : string {
		return $this->id;
	}

	/**
	 * Fetch PLC document.
	 *
	 * @return stdClass|Document
	 */
	public function fetch_document() {
		$url = static::DIRECTORY_URL . $this->id;
		$response = wp_remote_get( $url );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = json_decode( $response['body'] );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error( 'fair.packages.did.json_error', __( 'Unable to parse DID document response.', 'fair' ) );
		}

		$document = new Document(
			$data->id,
			$data->service ?? [],
			$data->verificationMethod ?? []
		);
		return $document;
	}
	// phpcs:enable
}
