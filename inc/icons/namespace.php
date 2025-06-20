<?php

namespace FAIR\Icons;

use FAIR;

function bootstrap(){
	add_filter( 'site_transient_update_plugins', __NAMESPACE__ . '\\set_default_icon', 99, 1 );
}

function set_default_icon( $transient ) {
	foreach( $transient->response as $updates){
		$updates->icons['default'] = plugin_dir_url( FAIR\PLUGIN_FILE ). 'inc/icons/svg.php';
	}

	return $transient;
}
