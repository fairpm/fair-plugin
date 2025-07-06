<?php
/**
 * DID Interface.
 *
 * @package FAIR
 */

namespace FAIR\Packages\DID;

interface DID {
	/**
	 * Get the DID type.
	 *
	 * One of plc, web.
	 */
	public function get_type() : string;

	/**
	 * Get the full decentralized ID (DID).
	 */
	public function get_id() : string;

	/**
	 * Get the method specific ID from DID.
	 */
	public function get_short_id() : string;

	/**
	 * Fetch the DID document.
	 *
	 * For most DIDs, this will be a remote request, so higher levels should
	 * cache this as appropriate.
	 *
	 * @return Document|\WP_Error
	 */
	public function fetch_document();
}
