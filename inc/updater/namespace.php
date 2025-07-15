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
	add_action( 'get_fair_document_data', __NAMESPACE__ . '\\get_fair_document_data', 10, 1 );
}

/**
 * Get FAIR MetadataDocument and ReleaseDocument data.
 *
 * Sets global variables for use in add_accept_header().
 *
 * @param stdClass $obj FAIR\Packages\Upgrader | FAIR\Updater\Updater.
 *
 * @return void
 */
function get_fair_document_data( $obj ) : void {
	global $metadata, $release;
	$metadata = $obj->metadata ?? $obj->package;
	$release = $obj->release;
}

/**
 * Send upgrader_pre_download filter to add_accept_header().
 *
 * @return bool
 */
function upgrader_pre_download() : bool {
	add_filter( 'http_request_args', __NAMESPACE__ . '\\add_accept_header', 20, 2 );
	return false; // upgrader_pre_download filter default return value.
}

/**
 * Add accept header for release asset package binary.
 *
 * ReleaseDocument artifact package content-type will be application/octet-stream.
 * Only for GitHub release assets.
 *
 * @global MetadataDocument $metadata
 * @global ReleaseDocument $release
 *
 * @param array  $args Array of http args.
 * @param string $url  Download URL.
 *
 * @return array
 */
function add_accept_header( $args, $url ) : array {
	global $metadata, $release;

	$accept_header = [];
	if ( ! str_contains( $url, 'api.github.com' ) ) {
		return $args;
	}
	foreach ( $release->artifacts->package[0] as $key => $value ) {
		$key = str_replace( '-', '_', $key );
		$package[ $key ] = $value;
	}
	if ( isset( $package['content_type'] ) && $package['content_type'] === 'application/octet-stream' ) {
		if ( ! empty( $accept_header ) && str_contains( $url, $metadata->slug ) ) {
			$args = array_merge( $args, $accept_header );
		}
	}

	return $args;
}

/**
 * Gather all plugins/themes with data in Update URI and DID header.
 *
 * @return array
 */
function get_packages() : array {
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
 * @param  array $icons Array of icon data.
 *
 * @return array
 */
function get_icons( $icons ) : array {
	if ( empty( $icons ) ) {
		return [];
	}

	$icons_arr = [];
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
		return [];
	}

	$icons_arr['1x'] = $regular->url ?? '';
	$icons_arr['2x'] = $high_res->url ?? '';
	$icons_arr['svg'] = $svg->url ?? '';

	return $icons_arr;
}

/**
 * Get banners.
 *
 * @param  array $banners Array of banner data.
 *
 * @return array
 */
function get_banners( $banners ) : array {
	if ( empty( $banners ) ) {
		return [];
	}

	$banners_arr = [];
	$regular = array_find( $banners, fn ( $banner ) => $banner->width === 772 && $banner->height === 250 );
	$high_res = array_find( $banners, fn ( $banner ) => $banner->width === 1544 && $banner->height === 500 );

	if ( empty( $regular ) && empty( $high_res ) ) {
		return [];
	}

	$banners_arr['low'] = $regular->url;
	$banners_arr['high'] = $high_res->url;

	return $banners_arr;
}

/**
 * Run FAIR\Updater\Updater for potential packages.
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
