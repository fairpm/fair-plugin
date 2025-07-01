<?php
/**
 * Get PLC Web document.
 *
 * @package FAIR
 */

namespace FAIR\Packages\DID;

/**
 * Class Web.
 */
class Web implements DID {
	const TYPE = 'web';

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
	 * Fetch PLC Web document.
	 *
	 * @return void|null
	 */
	public function fetch_document() {
		return null; // todo.
	}
}
