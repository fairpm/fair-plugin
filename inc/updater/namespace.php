<?php
/**
 * Update FAIR packages.
 *
 * @package FAIR
 */

namespace FAIR\Updater;

/**
 * Bootstrap.
 */
function bootstrap() {
	add_action( 'init', __NAMESPACE__ . '\\run' );
}

/**
 * Gather all plugins/themes with data in Update URI and DID header.
 *
 * @return stdClass
 */
function get_packages() {
	/**
	 * Packages.
	 *
	 * @var array
	 */
	$packages = [];

	// Seems to be required for PHPUnit testing on GitHub workflow.
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$plugin_path = trailingslashit( WP_PLUGIN_DIR );
	$plugins     = get_plugins();
	foreach ( $plugins as $file => $plugin ) {
		$plugin_id = get_file_data( $plugin_path . $file, [ 'PluginID' => 'Plugin ID' ] )['PluginID'];
		if ( ! empty( $plugin_id ) ) {
			$packages['plugins'][ $plugin_id ] = $plugin_path . $file;
		}
	}

	$theme_path = WP_CONTENT_DIR . '/themes/';
	$themes     = wp_get_themes();
	foreach ( $themes as $file => $theme ) {
		$theme_id = get_file_data( $theme_path . $file . '/style.css', [ 'ThemeID' => 'Theme ID' ] )['ThemeID'];
		if ( ! empty( $theme_id ) ) {
			$packages['themes'][ $theme_id ] = $theme_path . $file . '/style.css';
		}
	}

	return $packages;
}

/**
 * Get icons.
 *
 * @param  array $icon Array of icon data.
 *
 * @return array
 */
function get_icons( $icon ) {
	if ( empty( $icon ) ) {
		return;
	}

	$icons_arr = [];
	$icons = $icon;

	$regular = array_find( $icons, fn ( $icon ) => $icon->width === 772 && $icon->height === 250 );
	$high_res = array_find( $icons, fn ( $icon ) => $icon->width === 1544 && $icon->height === 500 );

	foreach ( $icons as $icon ) {
		foreach ( $icon as $mime => $type ) {
			if ( $mime === 'content-type' ) {
				if ( str_contains( $type, 'svg+xml' ) ) {
					$svg = $icon;
					break;
				}
			}
		}
	}

	if ( empty( $regular ) && empty( $high_res ) && empty( $svg ) ) {
		return;
	}

	$icons_arr['1x'] = $regular->url ?? '';
	$icons_arr['2x'] = $high_res->url ?? '';
	$icons_arr['svg'] = $svg->url ?? '';

	return $icons_arr;
}

/**
 * Get banners.
 *
 * @param  array $banner Banner data.
 *
 * @return array
 */
function get_banners( $banner ) {
	if ( empty( $banner ) ) {
		return [];
	}

	$banners_arr = [];
	$banners = $banner;

	$regular = array_find( $banners, fn ( $banner ) => $banner->width === 772 && $banner->height === 250 );
	$high_res = array_find( $banners, fn ( $banner ) => $banner->width === 1544 && $banner->height === 500 );

	if ( empty( $regular ) && empty( $high_res ) ) {
		return;
	}

	$banners_arr['low'] = $regular->url;
	$banners_arr['high'] = $high_res->url;

	return $banners_arr;
}

/**
 * Run Git Updater Lite for potential packages.
 *
 * @return void
 */
function run() {
	$packages = get_packages();
	$plugins = $packages['plugins'] ?? [];
	$themes = $packages['themes'] ?? [];
	$packages = array_merge( $plugins, $themes );
	foreach ( $packages as $did => $filepath ) {
		( new Updater( $did, $filepath ) )->run();
	}
}
