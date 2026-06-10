<?php
/**
 * Tests for FAIR\Updater\Updater::upgrader_source_selection().
 *
 * Verifies the directory renaming logic during plugin/theme upgrades:
 * sources named with DID hash suffixes are renamed to the proper slug,
 * while installs and errors pass through unchanged.
 *
 * @package FAIR
 */

declare( strict_types=1 );

namespace FAIR\Tests\Updater;

use FAIR\Updater\Updater;

// WordPress admin includes needed for Plugin_Upgrader / Theme_Upgrader.
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
require_once ABSPATH . 'wp-admin/includes/theme-install.php';

/**
 * Tests for Updater::upgrader_source_selection().
 *
 * @covers FAIR\Updater\Updater::upgrader_source_selection
 */
class UpgraderSourceSelectionTest extends \WP_UnitTestCase {

	private string $tmp_dir;
	private string $plugin_dir;

	protected function setUp(): void {
		parent::setUp();

		// Ensure wp_filesystem is loaded.
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			\WP_Filesystem();
		}

		// Create temp directories for testing.
		$this->tmp_dir    = sys_get_temp_dir() . '/fair-src-test-' . uniqid();
		$this->plugin_dir = sys_get_temp_dir() . '/fair-dst-test-' . uniqid();
		mkdir( $this->tmp_dir, 0755, true );
		mkdir( $this->plugin_dir, 0755, true );
	}

	protected function tearDown(): void {
		// Clean up temp directories.
		$this->rrmdir( $this->tmp_dir );
		$this->rrmdir( $this->plugin_dir );
		parent::tearDown();
	}

	/**
	 * Create a Plugin_Upgrader mock (empty subclass).
	 */
	private function plugin_upgrader(): \Plugin_Upgrader {
		return new class extends \Plugin_Upgrader {
			public function __construct() {
				// Skip parent constructor to avoid UI instantiation.
			}

			public function run( $options ) {
				return true;
			}
		};
	}

	/**
	 * Create a Theme_Upgrader mock.
	 */
	private function theme_upgrader(): \Theme_Upgrader {
		return new class extends \Theme_Upgrader {
			public function __construct() {
				// Skip parent constructor.
			}

			public function run( $options ) {
				return true;
			}
		};
	}

	/**
	 * Test that a WP_Error source is returned unchanged.
	 */
	public function test_should_return_wp_error_unchanged(): void {
		$error  = new \WP_Error( 'test_error', 'Simulated failure.' );
		$result = Updater::upgrader_source_selection(
			$error,
			$this->tmp_dir,
			$this->plugin_upgrader(),
			[ 'plugin' => 'my-plugin/my-plugin.php' ]
		);

		$this->assertSame( $error, $result, 'WP_Error source should pass through unchanged.' );
	}

	/**
	 * Test that an install action returns the source unchanged (no rename).
	 */
	public function test_should_return_source_unchanged_for_install_action(): void {
		$source = $this->tmp_dir . '/my-plugin-a1b2c3';

		$result = Updater::upgrader_source_selection(
			$source,
			$this->plugin_dir,
			$this->plugin_upgrader(),
			[ 'action' => 'install', 'plugin' => 'my-plugin/my-plugin.php' ]
		);

		$this->assertSame( $source, $result, 'Install action should not trigger rename.' );
	}

	/**
	 * Test that a non-Upgrader type throws TypeError.
	 */
	public function test_should_throw_type_error_for_non_upgrader(): void {
		$this->expectException( \TypeError::class );

		// Pass a generic WP_Upgrader (not Plugin/Theme) — should throw.
		$generic = new class extends \WP_Upgrader {
			public function run( $options ) {
				return true;
			}
		};

		Updater::upgrader_source_selection(
			$this->tmp_dir,
			$this->plugin_dir,
			$generic,
			[ 'plugin' => 'my-plugin/my-plugin.php' ]
		);
	}

	/**
	 * Test that a Plugin_Upgrader with matching basename returns source unchanged.
	 */
	public function test_plugin_should_return_source_when_basename_matches_slug(): void {
		// Create a source directory whose basename matches the plugin slug.
		$source = $this->tmp_dir . '/my-plugin';
		mkdir( $source, 0755, true );

		$result = Updater::upgrader_source_selection(
			$source,
			$this->plugin_dir,
			$this->plugin_upgrader(),
			[ 'plugin' => 'my-plugin/my-plugin.php' ]
		);

		$this->assertSame( $source, $result, 'When basename matches slug, no rename should occur.' );
	}

	/**
	 * Test that a Plugin_Upgrader renames a hash-suffixed source directory.
	 */
	public function test_plugin_should_rename_hash_suffixed_source(): void {
		// Create a source with a DID hash suffix (the typical case after unzip).
		$source = $this->tmp_dir . '/my-plugin-a1b2c3';
		mkdir( $source, 0755, true );
		file_put_contents( $source . '/test.txt', 'test content' );

		$result = Updater::upgrader_source_selection(
			$source,
			$this->plugin_dir,
			$this->plugin_upgrader(),
			[ 'plugin' => 'my-plugin/my-plugin.php' ]
		);

		$expected = trailingslashit( $this->plugin_dir ) . 'my-plugin';
		$this->assertSame( trailingslashit( $expected ), $result, 'Hash-suffixed source should be renamed to slug.' );

		// Verify the file moved.
		$this->assertFileExists( $expected . '/test.txt', 'File should exist at new location.' );
		$this->assertDirectoryDoesNotExist( $source, 'Old directory should no longer exist.' );
	}

	/**
	 * Test that a Theme_Upgrader with matching basename returns source unchanged.
	 */
	public function test_theme_should_return_source_when_basename_matches_slug(): void {
		$source = $this->tmp_dir . '/my-theme';
		mkdir( $source, 0755, true );

		$result = Updater::upgrader_source_selection(
			$source,
			$this->plugin_dir,
			$this->theme_upgrader(),
			[ 'theme' => 'my-theme' ]
		);

		$this->assertSame( $source, $result, 'When theme basename matches slug, no rename should occur.' );
	}

	/**
	 * Test that a Theme_Upgrader renames a hash-suffixed source directory.
	 */
	public function test_theme_should_rename_hash_suffixed_source(): void {
		$source = $this->tmp_dir . '/my-theme-d4e5f6';
		mkdir( $source, 0755, true );
		file_put_contents( $source . '/style.css', '/* test */' );

		$result = Updater::upgrader_source_selection(
			$source,
			$this->plugin_dir,
			$this->theme_upgrader(),
			[ 'theme' => 'my-theme' ]
		);

		$expected = trailingslashit( $this->plugin_dir ) . 'my-theme';
		$this->assertSame( trailingslashit( $expected ), $result, 'Hash-suffixed theme source should be renamed to slug.' );

		$this->assertFileExists( $expected . '/style.css', 'File should exist at new location.' );
		$this->assertDirectoryDoesNotExist( $source, 'Old directory should no longer exist.' );
	}

	/**
	 * Test that source already matching (case-insensitive) is not moved,
	 * but the returned path is normalized to lowercase slug.
	 */
	public function test_should_return_normalized_slug_when_source_case_differs(): void {
		$source = trailingslashit( $this->plugin_dir ) . 'My-Plugin';
		mkdir( $source, 0755, true );

		$result = Updater::upgrader_source_selection(
			rtrim( $source, '/' ),
			$this->plugin_dir,
			$this->plugin_upgrader(),
			[ 'plugin' => 'my-plugin/my-plugin.php' ]
		);

		$expected = trailingslashit( $this->plugin_dir ) . 'my-plugin';
		$this->assertSame( trailingslashit( $expected ), trailingslashit( $result ), 'Source with different case normalizes to slug.' );

		// The original directory should still exist (no move occurred).
		$this->assertDirectoryExists( $source, 'Original directory should not be moved when case-insensitively matching.' );
	}

	private function rrmdir( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$iter = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iter as $f ) {
			$f->isDir() ? rmdir( $f->getPathname() ) : unlink( $f->getPathname() );
		}
		rmdir( $dir );
	}
}
