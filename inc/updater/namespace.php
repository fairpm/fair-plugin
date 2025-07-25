<?php
/**
 * Update FAIR packages.
 *
 * @package FAIR
 */

namespace FAIR\Updater;

use FAIR\Packages;

const RELEASE_PACKAGES_CACHE_KEY = 'fair-release-packages';

/**
 * Bootstrap.
 */
function bootstrap() {
	add_action( 'init', __NAMESPACE__ . '\\run' );
}

/**
 * Add FAIR ReleaseDocument data to cache.
 *
 * @param string $did DID.
 * @return void
 */
function add_package_to_release_cache( string $did ) : void {
	if ( empty( $did ) ) {
		return;
	}
	$releases = wp_cache_get( RELEASE_PACKAGES_CACHE_KEY ) ?: [];
	$releases[ $did ] = Packages\get_latest_release_from_did( $did );
	wp_cache_set( RELEASE_PACKAGES_CACHE_KEY, $releases );
}

/**
 * Send upgrader_pre_download filter to add_accept_header().
 *
 * @param bool $false Whether to bail without returning the package.
 *                    Default false.
 * @return bool
 */
function upgrader_pre_download( $false ) : bool {
	add_filter( 'http_request_args', __NAMESPACE__ . '\\maybe_add_accept_header', 20, 2 );
	return $false;
}

/**
 * Maybe add accept header for release asset package binary.
 *
 * ReleaseDocument artifact package content-type will be application/octet-stream.
 * Only for GitHub release assets.
 *
 * @param array  $args Array of http args.
 * @param string $url  Download URL.
 *
 * @return array
 */
function maybe_add_accept_header( $args, $url ) : array {
	$releases = wp_cache_get( RELEASE_PACKAGES_CACHE_KEY ) ?: [];

	if ( ! str_contains( $url, 'api.github.com' ) ) {
		return $args;
	}

	foreach ( $releases as $release ) {
		if ( $url === $release->artifacts->package[0]->url ) {
			$content_type = $release->artifacts->package[0]->{'content-type'};
			if ( $content_type === 'application/octet-stream' ) {
				$args = array_merge( $args, [ 'headers' => [ 'Accept' => $content_type ] ] );
				break;
			}
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
