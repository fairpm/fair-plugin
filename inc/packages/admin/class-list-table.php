<?php
/**
 * Custom list table.
 *
 * @package FAIR
 */

namespace FAIR\Packages\Admin;

use FAIR\Packages;
use WP_Plugin_Install_List_Table;

/**
 * Custom plugin installer list table.
 */
class List_Table extends WP_Plugin_Install_List_Table {
	/**
	 * Generates the list table rows.
	 *
	 * @since 3.1.0
	 */
	public function display_rows() {
		ob_start();
		parent::display_rows();
		$res = ob_get_clean();

		// Find all DID slug classes, and add the *other* slug class.
		$res = preg_replace_callback( '/class="plugin-card plugin-card-([^ ]+)-(did--[^ ]+)"/', function ( $matches ) {
			$slug = $matches[1];
			$did = str_replace( '--', ':', $matches[2] );
			$hash = Packages\get_did_hash( $did );
			return sprintf(
				'class="plugin-card plugin-card-%1$s-%2$s plugin-card-%1$s-%3$s"',
				$slug,
				$matches[2],
				$hash
			);
		}, $res );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Raw HTML.
		echo $res;
	}
}
