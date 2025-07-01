<?php
/**
 * Create DID Document.
 *
 * @package FAIR
 */

namespace FAIR\Packages\DID;

use stdClass;

/**
 * Class Document.
 */
class Document {
	// phpcs:disable WordPress.NamingConventions.ValidVariableName
	/**
	 * DID.
	 *
	 * @var string
	 */
	public string $id;

	/**
	 * Service type.
	 *
	 * @var array
	 */
	public array $service;

	/**
	 * Verification method.
	 *
	 * @var array
	 */
	public array $verificationMethod;

	/**
	 * Constructor.
	 *
	 * @param  string $id DID.
	 * @param  array  $service Service type.
	 * @param  array $verificationMethod Verification method.
	 */
	public function __construct(
		string $id,
		array $service,
		array $verificationMethod
	) {
		$this->id = $id;
		$this->service = $service;
		$this->verificationMethod = $verificationMethod;
	}

	/**
	 * Get a service by type.
	 *
	 * @param string $type Service type.
	 * @return stdClass Service data, including id and serviceEndpoint
	 */
	public function get_service( string $type ) : ?stdClass {
		return array_find( $this->service, fn ( $service ) => $service->type === $type );
	}

	/**
	 * Get valid signing keys for FAIR.
	 *
	 * Gets valid keys from the document which can be used to sign packages.
	 *
	 * @return stdClass[] List of keys, including id and publicKeyMultibase
	 */
	public function get_fair_signing_keys() : array {
		return array_filter( $this->verificationMethod, function ( $key ) {
			// Only multibase keys are supported.
			if ( $key->type !== 'Multikey' ) {
				return false;
			}

			$parsed = parse_url( $key->id );

			// Only permit keys with IDs prefixed with 'fair'.
			return str_starts_with( $parsed['fragment'], 'fair' );
		} );
	}
	// phpcs:enable
}
