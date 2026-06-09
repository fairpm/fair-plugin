<?php
/**
 * Tests for FAIR\Updater\PluginPackage and ThemePackage.
 *
 * @package FAIR
 */

use FAIR\Updater\PluginPackage;
use FAIR\Updater\ThemePackage;

/**
 * Tests for FAIR\Updater\PluginPackage.
 *
 * @covers FAIR\Updater\PluginPackage
 * @covers FAIR\Updater\Package
 */
class PluginPackageTest extends WP_UnitTestCase {

	public function tear_down() {
		$this->delete_dir( WP_PLUGIN_DIR . '/my-test-plugin' );
		$this->delete_dir( WP_PLUGIN_DIR . '/my-awesome-plugin' );
		$this->delete_dir( WP_PLUGIN_DIR . '/vendor' );
		parent::tear_down();
	}

	private function delete_dir( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$iter = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iter as $file ) {
			$file->isDir() ? rmdir( $file->getPathname() ) : unlink( $file->getPathname() );
		}
		rmdir( $dir );
	}

	private function create_plugin_file( string $dir_name, string $version = '1.0.0' ): string {
		$path = WP_PLUGIN_DIR . "/{$dir_name}/{$dir_name}.php";
		wp_mkdir_p( dirname( $path ) );
		file_put_contents( $path, "<?php\n/**\n * Plugin Name: Test Plugin\n * Version: {$version}\n */" );
		return $path;
	}

	/**
	 * Test should construct with DID and filepath and parse version.
	 */
	public function test_should_construct_and_parse_version() {
		$file = $this->create_plugin_file( 'my-test-plugin', '2.5.0' );
		$plugin = new PluginPackage( 'did:plc:z72i7hdynmk6r22z27h6tvur', $file );

		$this->assertSame( 'did:plc:z72i7hdynmk6r22z27h6tvur', $plugin->did, 'DID should be set.' );
		$this->assertSame( $file, $plugin->filepath, 'Filepath should be set.' );
		$this->assertSame( '2.5.0', $plugin->local_version, 'local_version should be parsed from file header.' );
	}

	/**
	 * Test get_slug() should return plugin directory name.
	 */
	public function test_should_get_plugin_slug() {
		$file   = $this->create_plugin_file( 'my-awesome-plugin' );
		$plugin = new PluginPackage( 'did:plc:z72i7hdynmk6r22z27h6tvur', $file );

		$this->assertSame( 'my-awesome-plugin', $plugin->get_slug(), 'Slug should be the directory name.' );
	}

	/**
	 * Test get_relative_path() should return plugin basename.
	 */
	public function test_should_get_relative_path() {
		$file   = $this->create_plugin_file( 'my-test-plugin' );
		$plugin = new PluginPackage( 'did:plc:z72i7hdynmk6r22z27h6tvur', $file );

		$this->assertSame( 'my-test-plugin/my-test-plugin.php', $plugin->get_relative_path() );
	}

	/**
	 * Test get_slug() should handle deep nesting.
	 */
	public function test_should_handle_deeply_nested_plugin() {
		$dir  = WP_PLUGIN_DIR . '/vendor/namespace/deep-plugin';
		$file = $dir . '/deep-plugin.php';
		wp_mkdir_p( $dir );
		file_put_contents( $file, "<?php\n/**\n * Plugin Name: Deep Plugin\n * Version: 1.0.0\n */" );

		$plugin = new PluginPackage( 'did:plc:z72i7hdynmk6r22z27h6tvur', $file );

		$this->assertSame( 'vendor/namespace/deep-plugin', $plugin->get_slug(), 'Slug should include intermediate directories.' );
	}

	/**
	 * Test should allow setting local version after construction.
	 */
	public function test_should_set_local_version_property() {
		$file   = $this->create_plugin_file( 'my-test-plugin' );
		$plugin = new PluginPackage( 'did:plc:z72i7hdynmk6r22z27h6tvur', $file );

		$plugin->local_version = '3.0.0';

		$this->assertSame( '3.0.0', $plugin->local_version, 'local_version should be overridable.' );
	}
}

/**
 * Tests for FAIR\Updater\ThemePackage.
 *
 * @covers FAIR\Updater\ThemePackage
 */
class ThemePackageTest extends WP_UnitTestCase {

	public function tear_down() {
		$this->delete_dir( get_theme_root() . '/my-test-theme' );
		$this->delete_dir( get_theme_root() . '/my-fair-theme' );
		parent::tear_down();
	}

	private function delete_dir( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$iter = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iter as $file ) {
			$file->isDir() ? rmdir( $file->getPathname() ) : unlink( $file->getPathname() );
		}
		rmdir( $dir );
	}

	private function create_theme_file( string $dir_name, string $version = '1.0.0' ): string {
		$path = get_theme_root() . "/{$dir_name}/style.css";
		wp_mkdir_p( dirname( $path ) );
		file_put_contents( $path, "/*\nTheme Name: Test Theme\nVersion: {$version}\n*/" );
		return $path;
	}

	/**
	 * Test should construct with DID and filepath and parse version.
	 */
	public function test_should_construct_and_parse_version() {
		$file  = $this->create_theme_file( 'my-test-theme', '1.2.0' );
		$theme = new ThemePackage( 'did:plc:ppicmk23c5pimdivve34bcp2', $file );

		$this->assertSame( 'did:plc:ppicmk23c5pimdivve34bcp2', $theme->did, 'DID should be set.' );
		$this->assertSame( $file, $theme->filepath, 'Filepath should be set.' );
		$this->assertSame( '1.2.0', $theme->local_version, 'local_version should be parsed from file header.' );
	}

	/**
	 * Test get_slug() should return theme directory name.
	 */
	public function test_should_get_theme_slug() {
		$file  = $this->create_theme_file( 'my-fair-theme' );
		$theme = new ThemePackage( 'did:plc:ppicmk23c5pimdivve34bcp2', $file );

		$this->assertSame( 'my-fair-theme', $theme->get_slug(), 'Slug should be the theme directory name.' );
	}

	/**
	 * Test PluginPackage and ThemePackage should be distinct types.
	 */
	public function test_plugin_and_theme_packages_are_distinct() {
		$plugin_file = WP_PLUGIN_DIR . '/my-test-plugin/my-test-plugin.php';
		wp_mkdir_p( dirname( $plugin_file ) );
		file_put_contents( $plugin_file, "<?php\n/**\n * Plugin Name: P\n * Version: 1.0.0\n */" );

		$theme_file = get_theme_root() . '/my-test-theme/style.css';
		wp_mkdir_p( dirname( $theme_file ) );
		file_put_contents( $theme_file, "/*\nTheme Name: T\nVersion: 1.0.0\n*/" );

		$plugin = new PluginPackage( 'did:plc:aaa', $plugin_file );
		$theme  = new ThemePackage( 'did:plc:bbb', $theme_file );

		$this->assertNotInstanceOf( ThemePackage::class, $plugin );
		$this->assertNotInstanceOf( PluginPackage::class, $theme );
	}
}
