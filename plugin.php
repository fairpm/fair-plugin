<?php
/**
 * Plugin Name: FAIR - Federated and Independent Repositories
 * Description: Make your site more FAIR.
 * Version: 1.0.0
 * Author: FAIR Contributors
 * License: GPLv2
 * Requires at least: 5.4
 * Requires PHP: 7.4
 * Text Domain: fair
 * Domain Path: /languages
 * Update URI: https://api.fair.pm
 * GitHub Plugin URI: https://github.com/fairpm/fair-plugin
 * Primary Branch: main
 * Release Asset: true
 * Network: true
 */

namespace FAIR;

const VERSION = '1.0.0';
const PLUGIN_DIR = __DIR__;
const PLUGIN_FILE = __FILE__;

require_once __DIR__ . '/inc/namespace.php';
require_once __DIR__ . '/inc/avatars/namespace.php';
require_once __DIR__ . '/inc/credits/namespace.php';
require_once __DIR__ . '/inc/dashboard-widgets/namespace.php';
require_once __DIR__ . '/inc/default-repo/namespace.php';
require_once __DIR__ . '/inc/disable-openverse/namespace.php';
require_once __DIR__ . '/inc/icons/namespace.php';
require_once __DIR__ . '/inc/importers/namespace.php';
require_once __DIR__ . '/inc/packages/namespace.php';
require_once __DIR__ . '/inc/packages/admin/namespace.php';
require_once __DIR__ . '/inc/packages/admin/info.php';
require_once __DIR__ . '/inc/pings/namespace.php';
require_once __DIR__ . '/inc/salts/namespace.php';
require_once __DIR__ . '/inc/settings/namespace.php';
require_once __DIR__ . '/inc/upgrades/namespace.php';
require_once __DIR__ . '/inc/updater/namespace.php';
require_once __DIR__ . '/inc/user-notification/namespace.php';
require_once __DIR__ . '/inc/version-check/namespace.php';

// External dependencies.
require_once __DIR__ . '/inc/compatibility/php-polyfill.php';
require_once __DIR__ . '/inc/compatibility/wp-polyfill.php';
require_once __DIR__ . '/inc/updater/class-lite.php';

/**
 * Load translations.
 *
 * @return void
 */
function load_textdomain() {
	load_plugin_textdomain( 'fair', false, dirname( plugin_basename( PLUGIN_FILE ) ) . '/languages' );
}
add_action( 'init', __NAMESPACE__ . '\load_textdomain' );

bootstrap();
