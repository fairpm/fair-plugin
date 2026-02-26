<?php
/**
 * Update FAIR packages.
 *
 * @package FAIR
 */

namespace FAIR\Updater;

use const FAIR\CACHE_LIFETIME_FAILURE;
use const FAIR\Packages\CACHE_DID_FOR_INSTALL;
use const FAIR\Packages\CACHE_RELEASE_PACKAGES;
use const FAIR\Packages\CACHE_UPDATE_ERRORS;
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
	add_action( 'admin_init', __NAMESPACE__ . '\\register_plugin_row_hooks' );
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

	$themes = wp_get_themes();
	foreach ( $themes as $theme ) {
		$stylesheet_directory = $theme->get_stylesheet_directory();
		if ( empty( $stylesheet_directory ) ) {
			// The theme root is missing.
			continue;
		}

		$stylesheet_file = trailingslashit( $stylesheet_directory ) . 'style.css';
		$theme_id = get_file_data( $stylesheet_file, [ 'ThemeID' => 'Theme ID' ] )['ThemeID'];
		if ( ! empty( $theme_id ) ) {
			$packages['themes'][ $theme_id ] = $stylesheet_file;
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
	if ( ! Updater::should_run_on_current_page() ) {
		return;
	}

	$packages = get_packages();
	$plugins = $packages['plugins'] ?? [];
	$themes = $packages['themes'] ?? [];

	foreach ( $plugins as $did => $filepath ) {
		Updater::register_plugin( $did, $filepath );
	}

	foreach ( $themes as $did => $filepath ) {
		Updater::register_theme( $did, $filepath );
	}

	// Load hooks once for all packages.
	Updater::load_hooks();
}

/**
 * Register hooks to display update errors below plugin rows.
 */
function register_plugin_row_hooks(): void {
	$packages = get_packages();
	$plugins = $packages['plugins'] ?? [];

	foreach ( $plugins as $did => $path ) {
		$plugin_file = plugin_basename( $path );
		add_action(
			"after_plugin_row_{$plugin_file}",
			function ( $file, $plugin_data, $status ) use ( $did ) {
				display_plugin_update_error( $file, $plugin_data, $status, $did );
			},
			10,
			3
		);
	}
}

/**
 * Display a cached update error below the plugin row.
 *
 * @param string $plugin_file Path to the plugin file relative to the plugins directory.
 * @param array  $plugin_data An array of plugin data.
 * @param string $status      Status filter currently applied to the plugin list.
 * @param string $did         The DID of the plugin.
 */
function display_plugin_update_error( $plugin_file, $plugin_data, $status, $did ): void {
	$error = get_site_transient( CACHE_UPDATE_ERRORS . $did );
	if ( ! is_wp_error( $error ) ) {
		return;
	}

	$wp_list_table = _get_list_table( 'WP_Plugins_List_Table' );
	$colspan = $wp_list_table->get_column_count();

	// Calculate time remaining until retry.
	$error_data = $error->get_error_data();
	$timestamp = $error_data['timestamp'] ?? 0;
	$retry_time = $timestamp + CACHE_LIFETIME_FAILURE;
	$time_remaining = human_time_diff( time(), $retry_time );

	$message = sprintf(
		/* translators: %1$s: Error message, %2$s: Time period */
		__( 'Error: %1$s. Update checks paused for %2$s.', 'fair' ),
		$error->get_error_message(),
		$time_remaining,
	);

	$active_class = is_plugin_active( $plugin_file ) ? ' active' : '';

	printf(
		'<tr class="plugin-update-tr%1$s" id="fair-error-%2$s">
			<td colspan="%3$d" class="plugin-update colspanchange">
				<div class="update-message notice inline notice-error notice-alt"><p>%4$s</p></div>
			</td>
		</tr>',
		esc_attr( $active_class ),
		esc_attr( sanitize_title( $plugin_file ) ),
		esc_attr( $colspan ),
		esc_html( $message ),
	);
}

/**
 * Register hooks to display update errors below plugin rows.
 */
function register_plugin_row_hooks(): void {
	$packages = get_packages();
	$plugins = $packages['plugins'] ?? [];

	foreach ( $plugins as $did => $path ) {
		$plugin_file = plugin_basename( $path );
		add_action(
			"after_plugin_row_{$plugin_file}",
			function ( $file, $plugin_data, $status ) use ( $did ) {
				display_plugin_update_error( $file, $plugin_data, $status, $did );
			},
			10,
			3
		);
	}
}

/**
 * Display a cached update error below the plugin row.
 *
 * @param string $plugin_file Path to the plugin file relative to the plugins directory.
 * @param array  $plugin_data An array of plugin data.
 * @param string $status      Status filter currently applied to the plugin list.
 * @param string $did         The DID of the plugin.
 */
function display_plugin_update_error( $plugin_file, $plugin_data, $status, $did ): void {
	$error = get_site_transient( CACHE_UPDATE_ERRORS . $did );
	if ( ! is_wp_error( $error ) ) {
		return;
	}

	$wp_list_table = _get_list_table( 'WP_Plugins_List_Table' );
	$colspan = $wp_list_table->get_column_count();

	// Calculate time remaining until retry.
	$error_data = $error->get_error_data();
	$timestamp = $error_data['timestamp'] ?? 0;
	$retry_time = $timestamp + CACHE_LIFETIME_FAILURE;
	$time_remaining = human_time_diff( time(), $retry_time );

	$message = sprintf(
		/* translators: %1$s: Error message, %2$s: Time period */
		__( 'Error: %1$s. Update checks paused for %2$s.', 'fair' ),
		$error->get_error_message(),
		$time_remaining,
	);

	$active_class = is_plugin_active( $plugin_file ) ? ' active' : '';

	printf(
		'<tr class="plugin-update-tr%1$s" id="fair-error-%2$s">
			<td colspan="%3$d" class="plugin-update colspanchange">
				<div class="update-message notice inline notice-error notice-alt"><p>%4$s</p></div>
			</td>
		</tr>',
		esc_attr( $active_class ),
		esc_attr( sanitize_title( $plugin_file ) ),
		esc_attr( $colspan ),
		esc_html( $message ),
	);
}

/**
 * Download a package with signature verification.
 *
 * @param bool|string|WP_Error $reply      Whether to proceed with the download, the path to the downloaded package, or an existing WP_Error object.
 * @param string               $package    The URI of the package. If this is the full path to an existing local file, it will be returned untouched.
 * @param WP_Upgrader          $upgrader   The WP_Upgrader instance.
 * @param array                $hook_extra Extra hook data.
 * @return string|WP_Error The package path if the signature is valid, otherwise WP_Error.
 */
function verify_signature_on_download( $reply, string $package, WP_Upgrader $upgrader, $hook_extra ) {
	static $has_run = [];

	if ( false !== $reply || ( ! $upgrader instanceof Plugin_Upgrader && ! $upgrader instanceof Theme_Upgrader ) ) {
		return $reply;
	}

	$did = get_site_transient( CACHE_DID_FOR_INSTALL );
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

	$releases = get_site_transient( CACHE_RELEASE_PACKAGES ) ?? [];
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
 * Verify the DID in the extracted package matches the expected DID.
 *
 * Hooked to `upgrader_source_selection` at priority 9, before renaming hooks.
 *
 * @param string|WP_Error $source        File source location, or a WP_Error object.
 * @param string          $remote_source Remote file source location.
 * @param WP_Upgrader     $upgrader      WP_Upgrader instance.
 * @param array           $hook_extra    Extra arguments passed to hooked filters.
 * @return string|WP_Error The source path on success, WP_Error on failure.
 */
function verify_did_on_source_selection( $source, string $remote_source, WP_Upgrader $upgrader, $hook_extra ) {
	// Pass through errors from earlier hooks.
	if ( is_wp_error( $source ) ) {
		return $source;
	}

	if ( ! $upgrader instanceof Plugin_Upgrader && ! $upgrader instanceof Theme_Upgrader ) {
		return $source;
	}

	$expected_did = get_site_transient( CACHE_DID_FOR_INSTALL );
	if ( ! $expected_did ) {
		return $source;
	}

	$type = $upgrader instanceof Plugin_Upgrader ? 'plugin' : 'theme';

	$actual_did = Packages\get_did_by_path( $source, $type );

	if ( is_wp_error( $actual_did ) ) {
		return new WP_Error(
			'fair.packages.did_verification.not_found',
			sprintf(
				/* translators: %s: The expected DID. */
				__( 'Could not find a package ID in the downloaded package. Expected: %s', 'fair' ),
				$expected_did
			)
		);
	}

	if ( $actual_did->get_id() === $expected_did ) {
		return $source;
	}

	return new WP_Error(
		'fair.packages.did_verification.mismatch',
		sprintf(
			/* translators: %1$s: The expected DID, %2$s: The actual DID found. */
			__( 'Package ID mismatch. Expected: %1$s, found: %2$s', 'fair' ),
			$expected_did,
			$actual_did->get_id()
		)
	);
}

/**
 * Get trusted keys for signature verification.
 *
 * @return array
 */
function get_trusted_keys(): array {
	$did = get_site_transient( CACHE_DID_FOR_INSTALL );
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

	// FAIR uses Base58BTC-encoded Ed25519 keys.
	// Core expects base64-encoded keys.
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
