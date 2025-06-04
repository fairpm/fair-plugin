<?php

namespace FAIR\Packages\DID;

use stdClass;

class Document {
	public string $id;
	public array $service;
	public array $verificationMethod;

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
			if ( $key->type !== 'Multibase' ) {
				return false;
			}

			// Ensure this is a did:key
			$parsed = parse_url( $key->id );
			if ( $parsed['protocol'] !== 'did' || $parsed['host'] !== 'key' ) {
				return false;
			}

			// Only permit keys with IDs prefixed with 'fair'
			return str_starts_with( $parsed['fragment'], 'fair' );
		} );
	}
}
