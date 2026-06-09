<?php
/**
 * HTTP test bootstrap — runs inside Docker wp-cli container.
 *
 * Loads WordPress and the plugin. HTTP tests make real HTTP requests
 * to the WordPress container and verify HTTP-level behavior.
 *
 * @package FAIR
 */

// Composer autoloader.
$plugin_dir = '/var/www/html/wp-content/plugins/fair-plugin';
require_once "{$plugin_dir}/vendor/autoload.php";

// Load WordPress.
define( 'WP_USE_THEMES', false );
define( 'FAIR_DEFAULT_REPO_DOMAIN', 'mock-server:8080' );
define( 'FAIR_PLC_DIRECTORY_URL', 'http://mock-server:8080' );
$_SERVER['HTTP_HOST'] = 'integration.local';
require_once '/var/www/html/wp-load.php';

// Load the plugin.
require_once "{$plugin_dir}/plugin.php";

// Load admin includes needed by HTTP tests.
require_once ABSPATH . 'wp-admin/includes/user.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
