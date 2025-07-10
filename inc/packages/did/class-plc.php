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
	protected string $msid;

	/**
	 * Constructor.
	 *
	 * @param string $id DID.
	 * @param string $msid Method specific ID.
	 */
	public function __construct( string $id, string $msid ) {
		$this->id = $id;
		$this->msid = $msid;
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
	public function get_msid() : string {
		return $this->msid;
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
