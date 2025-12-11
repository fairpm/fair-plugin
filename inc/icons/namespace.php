<?php
/**
 * Adds a local source for default icon SVG.
 *
 * @package FAIR
 */

namespace FAIR\Icons;

use const FAIR\PLUGIN_FILE;

use stdClass;

/**
 * Bootstrap
 */
function bootstrap() {
	add_filter( 'site_transient_update_plugins', __NAMESPACE__ . '\\set_default_icon', 99, 1 );
}

/**
 * Set default icon in update transient.
 *
 * @param stdClass $transient Update transient.
 *
 * @return stdClass
 */
function set_default_icon( $transient ) {
	// The transient may not be set yet.
	if ( ! is_object( $transient ) ) {
		$transient = new stdClass();
	}

	if ( ! property_exists( $transient, 'response' ) || ! is_array( $transient->response ) ) {
		return $transient;
	}

	foreach ( $transient->response as $updates ) {
		$url = plugin_dir_url( PLUGIN_FILE ) . 'inc/icons/svg.php';
		$url = add_query_arg( 'color', set_random_color(), $url );

		if ( ! isset( $updates->icons ) || ! is_array( $updates->icons ) ) {
			$updates->icons = [];
		}

		$updates->icons['default'] = $url;
	}

	return $transient;
}

/**
 * Set random color.
 *
 * @return string
 */
function set_random_color() {
	$rand = str_pad( dechex( wp_rand( 0x000000, 0xFFFFFF ) ), 6, 0, STR_PAD_LEFT );

	return $rand;
}
