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
	const METHOD = 'plc';

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
	public function get_method() : string {
		return static::METHOD;
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
		if ( 200 !== wp_remote_retrieve_response_code( $response ) && property_exists( $data, 'message' ) ) {
			return new WP_Error( 'fair.packages.did.fetch.error', esc_html( $data->message ) );
		}
		if ( empty( $data->id ) || $data->id !== $this->id ) {
			return new WP_Error( 'fair.packages.did.fetch.mismatch', __( 'The PLC directory did not return the DID that was sent or the DID was invalid.', 'fair' ) );
		}

		$document = new Document(
			$data->id,
			$data->service ?? [],
			$data->verificationMethod ?? [],
			$data->alsoKnownAs ?? [],
		);
		return $document;
	}
	// phpcs:enable
}
