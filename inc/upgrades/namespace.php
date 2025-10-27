<?php
/**
 * Allows the plugin to handle any changes required during an upgrade.
 *
 * @package FAIR
 */

namespace FAIR\Upgrades;

use const FAIR\Avatars\AVATAR_SRC_SETTING_KEY;
use const FAIR\PLUGIN_FILE;
use const FAIR\VERSION;

/**
 * Bootstrap.
 */
function bootstrap() {
	add_action( 'upgrader_process_complete', __NAMESPACE__ . '\\run_plugin_upgrade_processes', 10, 2 );
}

/**
 * Monitor and run various upgrade processes based on the version.
 *
 * @param WP_Upgrader $upgrader   WP_Upgrader instance. In other contexts this might be a
 *                                Theme_Upgrader, Plugin_Upgrader, Core_Upgrade, or Language_Pack_Upgrader instance.
 * @param array       $hook_extra {
 *     Array of bulk item update data.
 *
 *     @type string $action       Type of action. Default 'update'.
 *     @type string $type         Type of update process. Accepts 'plugin', 'theme', 'translation', or 'core'.
 *     @type bool   $bulk         Whether the update process is a bulk update. Default true.
 *     @type array  $plugins      Array of the basename paths of the plugins' main files.
 *     @type array  $themes       The theme slugs.
 *     @type array  $translations {
 *         Array of translations update data.
 *
 *         @type string $language The locale the translation is for.
 *         @type string $type     Type of translation. Accepts 'plugin', 'theme', or 'core'.
 *         @type string $slug     Text domain the translation is for. The slug of a theme/plugin or
 *                                'default' for core translations.
 *         @type string $version  The version of a theme, plugin, or core.
 *     }
 * }
 *
 * @return void
 */
function run_plugin_upgrade_processes( $upgrader, $hook_extra ) {

	// Check for both possible keys.
	if ( empty( $hook_extra['plugins'] ) && empty( $hook_extra['plugin'] ) ) {
		return;
	}

	$pluginname = plugin_basename( PLUGIN_FILE );

	// Set a flag to begin.
	$do_upgrade = false;

	// Check the plural key first.
	if ( ! empty( $hook_extra['plugins'] ) && is_array( $hook_extra['plugins'] ) && in_array( $pluginname, $hook_extra['plugins'], true ) ) {
		$do_upgrade = true;
	}

	// Now look at the single key.
	if ( ! empty( $hook_extra['plugin'] ) && is_string( $hook_extra['plugin'] ) && $hook_extra['plugin'] === $pluginname ) {
		$do_upgrade = true;
	}

	// Bail now if we haven't met the criteria.
	if ( false === $do_upgrade ) {
		return;
	}

	switch ( VERSION ) {
		case '0.4.0':
			run_zero_four_zero_upgrade();
			break;
	}
}

/**
 * Run the avatar setting change at the 0.4.0 update.
 *
 * @return void
 */
function run_zero_four_zero_upgrade() {

	$org_option = get_option( 'fair_settings', [] );
	$define_src = ! empty( $org_option ) && array_key_exists( 'avatar_source', $org_option ) ? $org_option['avatar_source'] : 'fair';

	update_site_option( AVATAR_SRC_SETTING_KEY, $define_src );
	delete_option( 'fair_settings' );
}
