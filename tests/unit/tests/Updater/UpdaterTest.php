<?php
/**
 * Tests for FAIR\Updater\Updater static registry.
 *
 * @package FAIR
 */

use FAIR\Updater\Updater;
use FAIR\Updater\PluginPackage;
use FAIR\Updater\ThemePackage;

/**
 * Tests for FAIR\Updater\Updater static registry methods.
 *
 * @covers FAIR\Updater\Updater::register_plugin
 * @covers FAIR\Updater\Updater::register_theme
 * @covers FAIR\Updater\Updater::get_plugin
 * @covers FAIR\Updater\Updater::get_theme
 * @covers FAIR\Updater\Updater::get_plugins
 * @covers FAIR\Updater\Updater::get_themes
 * @covers FAIR\Updater\Updater::get_plugin_by_file
 * @covers FAIR\Updater\Updater::get_plugin_by_slug
 * @covers FAIR\Updater\Updater::get_theme_by_slug
 */
class UpdaterRegistryTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		$this->create_plugin_file( 'my-plugin' );
		$this->create_plugin_file( 'plugin1', 'plugin1.php' );
		$this->create_plugin_file( 'plugin2', 'plugin2.php' );
	}

	public function tear_down() {
		$this->delete_dir( WP_PLUGIN_DIR . '/my-plugin' );
		$this->delete_dir( WP_PLUGIN_DIR . '/plugin1' );
		$this->delete_dir( WP_PLUGIN_DIR . '/plugin2' );
		$this->delete_dir( get_theme_root() . '/my-theme' );
		$this->reset_registry();
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

	private function create_plugin_file( string $dir_name, string $filename = null ): string {
		$filename = $filename ?? "{$dir_name}.php";
		$path     = WP_PLUGIN_DIR . "/{$dir_name}/{$filename}";
		wp_mkdir_p( dirname( $path ) );
		file_put_contents( $path, "<?php\n/**\n * Plugin Name: Test\n * Version: 1.0.0\n */" );
		return $path;
	}

	private function create_theme_file( string $dir_name ): string {
		$path = get_theme_root() . "/{$dir_name}/style.css";
		wp_mkdir_p( dirname( $path ) );
		file_put_contents( $path, "/*\nTheme Name: Test\nVersion: 1.0.0\n*/" );
		return $path;
	}

	private function reset_registry(): void {
		Updater::reset();
	}

	/**
	 * Test should register and retrieve a plugin by DID.
	 */
	public function test_should_register_and_get_plugin() {
		$did  = 'did:plc:z72i7hdynmk6r22z27h6tvur';
		$file = WP_PLUGIN_DIR . '/my-plugin/my-plugin.php';

		Updater::register_plugin( $did, $file );

		$plugin = Updater::get_plugin( $did );

		$this->assertNotNull( $plugin, 'Registered plugin should be retrievable.' );
		$this->assertInstanceOf( PluginPackage::class, $plugin );
		$this->assertSame( $did, $plugin->did );
		$this->assertSame( $file, $plugin->filepath );
	}

	/**
	 * Test should register and retrieve a theme by DID.
	 */
	public function test_should_register_and_get_theme() {
		$this->create_theme_file( 'my-theme' );
		$did  = 'did:plc:ppicmk23c5pimdivve34bcp2';
		$file = get_theme_root() . '/my-theme/style.css';

		Updater::register_theme( $did, $file );

		$theme = Updater::get_theme( $did );

		$this->assertNotNull( $theme, 'Registered theme should be retrievable.' );
		$this->assertInstanceOf( ThemePackage::class, $theme );
		$this->assertSame( $did, $theme->did );
	}

	/**
	 * Test should return null for unregistered plugin DID.
	 */
	public function test_should_return_null_for_unknown_plugin_did() {
		$this->assertNull( Updater::get_plugin( 'did:plc:nonexistent' ) );
	}

	/**
	 * Test should return null for unregistered theme DID.
	 */
	public function test_should_return_null_for_unknown_theme_did() {
		$this->assertNull( Updater::get_theme( 'did:plc:nonexistent' ) );
	}

	/**
	 * Test should overwrite when registering same plugin DID twice.
	 */
	public function test_should_overwrite_on_duplicate_plugin_registration() {
		$did   = 'did:plc:z72i7hdynmk6r22z27h6tvur';
		$file1 = WP_PLUGIN_DIR . '/plugin1/plugin1.php';
		$file2 = WP_PLUGIN_DIR . '/plugin2/plugin2.php';

		Updater::register_plugin( $did, $file1 );
		Updater::register_plugin( $did, $file2 );

		$plugin = Updater::get_plugin( $did );

		$this->assertSame( $file2, $plugin->filepath, 'Second registration should overwrite filepath.' );
	}

	/**
	 * Test should return all registered plugins.
	 */
	public function test_should_return_all_registered_plugins() {
		$file1 = WP_PLUGIN_DIR . '/plugin1/plugin1.php';
		$file2 = WP_PLUGIN_DIR . '/plugin2/plugin2.php';

		Updater::register_plugin( 'did:plc:aaa', $file1 );
		Updater::register_plugin( 'did:plc:bbb', $file2 );

		$all = Updater::get_plugins();

		$this->assertCount( 2, $all, 'Should return all registered plugins.' );
		$this->assertArrayHasKey( 'did:plc:aaa', $all );
		$this->assertArrayHasKey( 'did:plc:bbb', $all );
	}

	/**
	 * Test should return all registered themes.
	 */
	public function test_should_return_all_registered_themes() {
		$this->create_theme_file( 'my-theme' );
		$file = get_theme_root() . '/my-theme/style.css';

		Updater::register_theme( 'did:plc:xxx', $file );
		Updater::register_theme( 'did:plc:yyy', $file );

		$all = Updater::get_themes();

		$this->assertCount( 2, $all, 'Should return all registered themes.' );
	}

	/**
	 * Test should return empty arrays when nothing registered.
	 */
	public function test_should_return_empty_arrays_when_nothing_registered() {
		$this->assertCount( 0, Updater::get_plugins() );
		$this->assertCount( 0, Updater::get_themes() );
	}

	/**
	 * Test should find plugin by basename file path.
	 */
	public function test_should_find_plugin_by_relative_file() {
		$did  = 'did:plc:z72i7hdynmk6r22z27h6tvur';
		$file = WP_PLUGIN_DIR . '/my-plugin/my-plugin.php';

		Updater::register_plugin( $did, $file );

		$plugin = Updater::get_plugin_by_file( 'my-plugin/my-plugin.php' );

		$this->assertNotNull( $plugin, 'Should find plugin by relative file.' );
		$this->assertSame( $did, $plugin->did, 'Found plugin should have correct DID.' );
	}

	/**
	 * Test should return null for unknown plugin file.
	 */
	public function test_should_return_null_for_unknown_plugin_file() {
		$this->assertNull( Updater::get_plugin_by_file( 'unknown/unknown.php' ) );
	}
}

/**
 * Tests for Updater::should_run_on_current_page().
 *
 * @covers FAIR\Updater\Updater::should_run_on_current_page
 */
class UpdaterShouldRunTest extends WP_UnitTestCase {

	/**
	 * Test should return true on plugins.php.
	 */
	public function test_should_run_on_plugins_page() {
		$this->mock_pagenow( 'plugins.php' );
		$this->assertTrue( Updater::should_run_on_current_page() );
	}

	/**
	 * Test should return true on themes.php.
	 */
	public function test_should_run_on_themes_page() {
		$this->mock_pagenow( 'themes.php' );
		$this->assertTrue( Updater::should_run_on_current_page() );
	}

	/**
	 * Test should return true on update-core.php.
	 */
	public function test_should_run_on_update_core_page() {
		$this->mock_pagenow( 'update-core.php' );
		$this->assertTrue( Updater::should_run_on_current_page() );
	}

	/**
	 * Test should return true on update.php.
	 */
	public function test_should_run_on_update_page() {
		$this->mock_pagenow( 'update.php' );
		$this->assertTrue( Updater::should_run_on_current_page() );
	}

	/**
	 * Test should return true on plugin-install.php.
	 */
	public function test_should_run_on_plugin_install_page() {
		$this->mock_pagenow( 'plugin-install.php' );
		$this->assertTrue( Updater::should_run_on_current_page() );
	}

	/**
	 * Test should return true on admin-ajax.php.
	 */
	public function test_should_run_on_ajax() {
		$this->mock_pagenow( 'admin-ajax.php' );
		$this->assertTrue( Updater::should_run_on_current_page() );
	}

	/**
	 * Test should return false on unrelated pages.
	 */
	public function test_should_not_run_on_unrelated_page() {
		$this->mock_pagenow( 'edit.php' );
		$this->assertFalse( Updater::should_run_on_current_page() );
	}

	/**
	 * Test should return false on post.php.
	 */
	public function test_should_not_run_on_post_page() {
		$this->mock_pagenow( 'post.php' );
		$this->assertFalse( Updater::should_run_on_current_page() );
	}

	/**
	 * Mock the global $pagenow variable.
	 */
	private function mock_pagenow( string $page ): void {
		global $pagenow;
		$pagenow = $page;
	}
}
