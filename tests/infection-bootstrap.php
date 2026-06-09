<?php
/**
 * Infection bootstrap — minimal WordPress stubs + FAIR autoloader.
 *
 * Infection uses reflection to analyze all source files. This file:
 * 1. Defines WordPress constants/functions/classes that FAIR code
 *    references but aren't available outside WordPress.
 * 2. Registers the FAIR autoloader so source classes can be reflected.
 */

// ── WordPress constants ────────────────────────────────────────────

if ( ! defined( 'WPINC' ) ) {
	define( 'WPINC', true );
}
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
}
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}

// ── Stub classes for external dependencies ─────────────────────────
// These classes would normally be loaded by the project but extend WP
// or external types that don't exist in infection's context.

if ( ! class_exists( 'Fragen\\Git_Updater\\Lite' ) ) {
	class Fragen_Git_Updater_Lite_stub {}
	class_alias( Fragen_Git_Updater_Lite_stub::class, 'Fragen\\Git_Updater\\Lite' );
}

// WordPress classes used by `extends` or `use` imports.
if ( ! class_exists( 'WP_List_Table' ) ) {
	class WP_List_Table { public $items = []; }
}
if ( ! class_exists( 'WP_Plugin_Install_List_Table' ) ) {
	class WP_Plugin_Install_List_Table extends WP_List_Table {}
}
if ( ! class_exists( 'WP_Upgrader' ) ) {
	class WP_Upgrader {}
}
if ( ! class_exists( 'Plugin_Upgrader' ) ) {
	class Plugin_Upgrader {}
}
if ( ! class_exists( 'Theme_Upgrader' ) ) {
	class Theme_Upgrader {}
}
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		/** @var string[] */
		public array $errors = [];
		/** @var string[] */
		public array $error_data = [];
		public function __construct( string $code = '', string $message = '', mixed $data = '' ) {}
		public function get_error_codes(): array { return []; }
		public function get_error_messages(): array { return []; }
	}
}

// WordPress functions used by FAIR code.
if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string { return $text; }
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( string $text ): string { return $text; }
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( string $text ): string { return $text; }
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( string $url ): string { return $url; }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( string $text ): string { return $text; }
}
if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( mixed $value ): mixed { return $value; }
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( mixed $data, int $options = 0, int $depth = 512 ): string|false {
		return json_encode( $data, $options, $depth );
	}
}
if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( string $url, int $component = -1 ): mixed {
		return parse_url( $url, $component );
	}
}
if ( ! function_exists( 'wp_doing_ajax' ) ) {
	function wp_doing_ajax(): bool { return false; }
}
if ( ! function_exists( 'get_site_transient' ) ) {
	function get_site_transient( string $key ): mixed { return false; }
}
if ( ! function_exists( 'set_site_transient' ) ) {
	function set_site_transient( string $key, mixed $value, int $expiration = 0 ): bool { return true; }
}
if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( string $key ): mixed { return false; }
}

// ── FAIR autoloader ────────────────────────────────────────────────

if ( file_exists( __DIR__ . '/../vendor/autoload.php' ) ) {
	require_once __DIR__ . '/../vendor/autoload.php';
}

// Register the custom FAIR class autoloader.
$base_dir = dirname( __DIR__ ) . '/inc/';
spl_autoload_register( function ( string $class ) use ( $base_dir ): void {
	$prefix = 'FAIR';
	if ( ! str_starts_with( $class, $prefix . '\\' ) ) {
		return;
	}

	$relative_class = substr( $class, strlen( $prefix ) + 1 );
	$relative_class = strtolower( $relative_class );
	$relative_class = str_replace( '_', '-', $relative_class );

	$file = '';
	$last_ns_pos = strripos( $relative_class, '\\' );
	if ( $last_ns_pos !== false ) {
		$namespace = substr( $relative_class, 0, $last_ns_pos );
		$relative_class = substr( $relative_class, $last_ns_pos + 1 );
		$file = str_replace( '\\', DIRECTORY_SEPARATOR, $namespace ) . DIRECTORY_SEPARATOR;
	}
	$file .= 'class-' . $relative_class . '.php';

	$path = $base_dir . $file;
	if ( file_exists( $path ) ) {
		require_once $path;
	}
} );
