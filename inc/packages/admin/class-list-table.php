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
	 * Replace Add Plugins message with ours.
	 *
	 * Skip for WP versions prior to 6.9.0.
	 *
	 * @since WordPress 6.9.0
	 * @return void
	 */
	public function views() {
		if ( ! is_wp_version_compatible( '6.9' ) ) {
			parent::views();
			return;
		}

		ob_start();
		parent::views();
		$views = ob_get_clean();

		preg_match( '|<a href="(?<url>[^"]+)">(?<text>[^>]+)<\/a>|', $views, $matches );
		if ( ! empty( $matches['text'] ) ) {
			$text_with_fair = str_replace( 'WordPress', 'FAIR', $matches['text'] );
			$str = str_replace(
				[ $matches['url'], $matches['text'] ],
				[ __( 'https://fair.pm/packages/plugins/', 'fair' ), $text_with_fair ],
				$matches[0]
			);
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Replacements are escaped. The previous content is direct from Core.
		echo str_replace( $matches[0], $str, $views );
	}

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
