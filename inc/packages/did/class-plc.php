<?php
/**
 * Get PLC document.
 *
 * @package FAIR
 */

namespace FAIR\Packages\DID;

use WP_Error;

/**
 * Class PLC.
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
	 * Method specific ID.
	 *
	 * @var string
	 */
	protected string $short_id;

	/**
	 * Constructor.
	 *
	 * @param string $id DID.
	 * @param string $short_id Method specific ID.
	 */
	public function __construct( string $id, string $short_id ) {
		$this->id = $id;
		$this->short_id = $short_id;
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
	 * Get the method specific ID from DID.
	 */
	public function get_short_id() : string {
		return $this->short_id;
	}

	/**
	 * Fetch PLC document.
	 *
	 * @return Document|WP_Error
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
