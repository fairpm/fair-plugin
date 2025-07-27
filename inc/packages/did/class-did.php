<?php
/**
 * DID Interface.
 *
 * @package FAIR
 */

namespace FAIR\Packages\DID;

interface DID {
	/**
	 * Get the DID method.
	 *
	 * One of plc, web.
	 */
	public function get_method() : string;

	/**
	 * Get the full decentralized ID (DID).
	 */
	public function get_id() : string;

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
