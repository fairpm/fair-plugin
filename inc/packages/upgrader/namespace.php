<?php

namespace FAIR\Packages\Upgrader;

use Fragen\Git_Updater;
use stdClass;

/**
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

function bootstrap() {
	add_action( 'init', __NAMESPACE__ . '\\init' );
}

/**
 * Gather all plugins/themes with data in Update URI and DID header.
 *
 * @return stdClass
 */
function init() {
	/** @var array */
	$package_arr = array();

	// Seems to be required for PHPUnit testing on GitHub workflow.
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$plugin_path = trailingslashit( WP_PLUGIN_DIR );
	$plugins     = get_plugins();
	foreach ( $plugins as $file => $plugin ) {
		$update_uri = $plugin['UpdateURI'];
		$plugin_id  = get_file_data( $plugin_path . $file, array( 'PluginID' => 'Plugin ID' ) )['PluginID'];

		if ( ! empty( $update_uri ) && ! empty( $plugin_id ) ) {
			$package_arr[] = $plugin_path . $file;
		}
	}

	$theme_path = WP_CONTENT_DIR . '/themes/';
	$themes     = wp_get_themes();
	foreach ( $themes as $file => $theme ) {
		$update_uri = $theme->get( 'UpdateURI' );
		$theme_id   = get_file_data( $theme_path . $file . '/style.css', array( 'ThemeID' => 'Theme ID' ) )['ThemeID'];

		if ( ! empty( $update_uri ) && ! empty( $theme_id ) ) {
			$package_arr[] = $theme_path . $file . '/style.css';
		}
	}

	run( $package_arr );
}

/**
 * Run Git Updater Lite for potential packages.
 *
 * @return void
 */
function run( $package_arr ) {
	foreach ( $package_arr as $package ) {
		( new Git_Updater\Lite( $package ) )->run();
	}
}
