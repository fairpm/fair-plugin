<?php

namespace FAIR\Packages\DID;

class Web implements DID {
	const TYPE = 'web';

	/**
	 * Decentralized ID.
	 */
	protected string $id;

	/**
	 * Constructor.
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

	public function fetch_document() {
		return null; // todo
	}
}
