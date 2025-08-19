<?php
/**
 * Update FAIR packages.
 *
 * @package FAIR
 */

namespace FAIR\Updater;

use const FAIR\Packages\CACHE_DID_FOR_INSTALL;
use const FAIR\Packages\CACHE_RELEASE_PACKAGES;
use FAIR\Packages;
use function FAIR\is_wp_cli;
use Plugin_Upgrader;
use Theme_Upgrader;
use WP_CLI;
use WP_Error;
use WP_Upgrader;

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
 * Download a package with signature verification.
 *
 * @param bool|string|WP_Error $reply      Whether to proceed with the download, the path to the downloaded package, or an existing WP_Error object. Default true.
 * @param string               $package    The URI of the package. If this is the full path to an existing local file, it will be returned untouched.
 * @param WP_Upgrader          $upgrader   The WP_Upgrader instance.
 * @param array                $hook_extra Extra hook data.
 * @return true|WP_Error True if the signature is valid, otherwise WP_Error.
 */
function verify_signature_on_download( $reply, string $package, WP_Upgrader $upgrader, $hook_extra ) {
	static $has_run = [];

	if ( false !== $reply || ( ! $upgrader instanceof Plugin_Upgrader && ! $upgrader instanceof Theme_Upgrader ) ) {
		return $reply;
	}

	$did = get_transient( CACHE_DID_FOR_INSTALL );
	if ( ! $did ) {
		return $reply;
	}

	// This method is hooked to 'upgrader_pre_download', which is used in WP_Upgrader::download_package().
	// Bailing on subsequent runs for the same package URI prevents an infinite loop.
	$key = sha1( $did . '_' . $package );
	if ( isset( $has_run[ $key ] ) ) {
		return $reply;
	}
	$has_run[ $key ] = true;

	// Local files should be returned untouched.
	if ( ! preg_match( '!^(http|https|ftp)://!i', $package ) && file_exists( $package ) ) {
		return $package;
	}

	$releases = get_transient( CACHE_RELEASE_PACKAGES ) ?? [];
	if ( empty( $releases ) || ! isset( $releases[ $did ] ) ) {
		return $reply;
	}

	$artifact = Packages\pick_artifact_by_lang( $releases[ $did ]->artifacts->package );
	if ( ! $artifact || $package !== $artifact->url ) {
		return $reply;
	}

	$path = $upgrader->download_package( $package, false, $hook_extra );
	if ( is_wp_error( $path ) ) {
		return $path;
	}

	add_filter( 'wp_trusted_keys', __NAMESPACE__ . '\\get_trusted_keys', 100 );
	$decoded_base64url = sodium_base642bin( $artifact->signature, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING );
	$result = verify_file_signature( $path, base64_encode( $decoded_base64url ) );
	remove_filter( 'wp_trusted_keys', __NAMESPACE__ . '\\get_trusted_keys', 100 );

	if ( $result === true ) {
		if ( is_wp_cli() ) {
			WP_CLI::success(
				sprintf(
					/* translators: %s: The DID of the package. */
					__( 'Verified signature for %s', 'fair' ),
					$did
				)
			);
		}
		return $path;
	}

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return new WP_Error(
		'fair.packages.signature_verification.failed',
		sprintf(
			/* translators: %s: The package's URL. */
			__( 'Signature verification could not be performed for the package: %s', 'fair' ),
			$package
		)
	);
}

/**
 * Get trusted keys for signature verification.
 *
 * @return array
 */
function get_trusted_keys(): array {
	$did = get_transient( CACHE_DID_FOR_INSTALL );
	if ( ! $did ) {
		return [];
	}

	$doc = Packages\get_did_document( $did );
	if ( is_wp_error( $doc ) ) {
		return [];
	}

	$keys = $doc->get_fair_signing_keys();
	if ( empty( $keys ) ) {
		return [];
	}

	/*
		* FAIR uses Base58BTC-encoded Ed25519 keys.
		* Core expects base64-encoded keys.
		*/
	$recoded_keys = [];
	foreach ( $keys as $key ) {
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$str = Base58BTC::decode( $key->publicKeyMultibase );

		// Ed25519 keys only.
		if ( substr( $str, 0, 2 ) !== "\xed\x01" ) {
			continue;
		}

		$key_material = substr( $str, 2 );
		$recoded_keys[] = base64_encode( $key_material );
	}

	return $recoded_keys;
}

/**
 * Move a package to the correctly named directory during installation.
 *
 * @param string      $source        Path of $source.
 * @param string      $remote_source Path of $remote_source.
 * @param WP_Upgrader $upgrader      An Upgrader object.
 * @param array       $hook_extra    Array of hook data.
 * @return string The correct directory path for installation.
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
