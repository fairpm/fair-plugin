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
	 * Fetch PLC Web document.
	 *
	 * @return void|null
	 */
	public function fetch_document() {
		return null; // todo.
	}
}
