<?php

namespace FAIR\Icons;

use FAIR;

function bootstrap(){
	add_filter( 'site_transient_update_plugins', __NAMESPACE__ . '\\set_default_icon', 99, 1 );
}

function set_default_icon( $transient ) {
	foreach( $transient->response as $updates ) {
		$url = plugin_dir_url( FAIR\PLUGIN_FILE ) . 'inc/icons/svg.php';
		$url = add_query_arg( 'color', set_random_color(), $url );
		$updates->icons['default'] = $url;
	}

	return $transient;
}

function set_random_color() {
	$rand = str_pad( dechex( rand( 0x000000, 0xFFFFFF ) ), 6, 0, STR_PAD_LEFT );

	return $rand;
}
