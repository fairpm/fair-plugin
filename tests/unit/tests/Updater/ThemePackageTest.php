<?php
/**
 * Tests for FAIR\Updater\ThemePackage.
 *
 * @package FAIR
 */

use FAIR\Updater\PluginPackage;
use FAIR\Updater\ThemePackage;

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
