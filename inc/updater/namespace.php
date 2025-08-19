<?php
/**
 * Update FAIR packages.
 *
 * @package FAIR
 */

namespace FAIR\Updater;

use FAIR\Packages;
use WP_Error;

/**
 * Bootstrap.
 */
function bootstrap() {
	add_action( 'init', __NAMESPACE__ . '\\run' );
	add_filter( 'upgrader_source_selection', __NAMESPACE__ . '\\move_package_during_install', 10, 4 );
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

/**
 * Move a package to the correctly named directory during installation.
 *
 * @param string      $source        Path of $source.
 * @param string      $remote_source Path of $remote_source.
 * @param WP_Upgrader $upgrader      An Upgrader object.
 * @param array       $hook_extra    Array of hook data.
 *
 * @return string|WP_Error The correct directory path for installation, or WP_Error on failure.
 */
function move_package_during_install( $source, $remote_source, $upgrader, $hook_extra ): string {
	global $wp_filesystem;

	if ( isset( $hook_extra['action'] ) && $hook_extra['action'] !== 'install' ) {
		// Other actions are handled elsewhere.
		return $source;
	}

	if ( ! in_array( $hook_extra['type'], [ 'plugin', 'theme' ], true ) ) {
		// This package type is not supported.
		return $source;
	}

	$did = get_did_by_path( $source, $hook_extra['type'] );
	if ( is_wp_error( $did ) ) {
		// This isn't a valid FAIR package.
		return $source;
	}

	$did_hash = Packages\get_did_hash( $did->get_id() );
	if ( str_ends_with( $source, "{$did_hash}/" ) ) {
		// The directory name is already correct.
		return $source;
	}

	$new_source = untrailingslashit( $source ) . "-{$did_hash}/";
	// Core must be able to find the new source directory.
	$wp_filesystem->move( $source, $new_source, true );

	return $new_source;
}

/**
 * Get a package's DID by its path.
 *
 * @param string $path The absolute path to the package's directory or main file.
 * @param string $type The type of package. Allowed types are 'plugin' or 'theme'.
 * @return DID|WP_Error The DID object on success, WP_Error on failure.
 */
function get_did_by_path( $path, $type ) {
	global $wp_filesystem;

	if ( $type === 'theme' ) {
		if ( ! str_ends_with( $path, 'style.css' ) ) {
			$path = trailingslashit( $path ) . 'style.css';
		}

		$id = get_file_data( $path, [ 'id' => 'Theme ID' ] )['id'];
		if ( $id ) {
			return Packages\parse_did( $id );
		}
	}

	if ( $type === 'plugin' ) {
		if ( str_ends_with( $path, '.php' ) ) {
			$id = get_file_data( $path, [ 'id' => 'Plugin ID' ] )['id'];
			return Packages\parse_did( $id );
		}

		$files = $wp_filesystem->dirlist( $path ) ?: false;
		if ( ! $files ) {
			// Finding a DID is impossible.
			return new WP_Error( 'fair.packages.dirlist_failed', __( "The package's file list could not be retrieved.", 'fair' ) );
		}

		foreach ( $files as $filename => $data ) {
			if ( $data['type'] !== 'f' || ! str_ends_with( $filename, '.php' ) ) {
				continue;
			}

			$filepath = trailingslashit( $path ) . $filename;
			$id = get_file_data( $filepath, [ 'id' => 'Plugin ID' ] )['id'];
			if ( $id ) {
				return Packages\parse_did( $id );
			}
		}
	}

	return new WP_Error( 'fair.packages.none_found', __( 'No FAIR packages were found.', 'fair' ) );
}
