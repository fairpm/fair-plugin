<?php

/* Path to the WordPress Core codebase. */
if ( file_exists( dirname( __DIR__, 2 ) . '/wp-load.php' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 2 ) . '/');
} elseif ( file_exists( dirname( __DIR__, 3 ) . '/wp-load.php' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 3 ) . '/');
}

// Test with multisite enabled: (previously -m)
// define( 'WP_TESTS_MULTISITE', true );

// Force known bugs: (previously -f)
// define( 'WP_TESTS_FORCE_KNOWN_BUGS', true );

// Test with WordPress debug mode on (previously -d)
// define( 'WP_DEBUG', true );

// ** MySQL settings ** //

// This configuration file will be used by the copy of WordPress being tested.
// wordpress/wp-config.php will be ignored.

// WARNING WARNING WARNING!
// These tests will DROP ALL TABLES in the database with the prefix named below.
// DO NOT use a production database or one that is shared with something else.

define( 'DB_NAME', 'tests-wordpress' );
define( 'DB_USER', 'root' );
define( 'DB_PASSWORD', 'password' );
define( 'DB_HOST', 'tests-mysql' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

$table_prefix  = 'wptests_';   // Only numbers, letters, and underscores please!

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );

define( 'WP_PHP_BINARY', 'php' );

define( 'WPLANG', '' );
