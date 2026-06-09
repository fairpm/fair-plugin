<?php
/**
 * Tests for FAIR\Updater\get_packages().
 *
 * @package FAIR
 */

use function FAIR\Updater\get_packages;

/**
 * Tests for FAIR\Updater\get_packages().
 *
 * @covers FAIR\Updater\get_packages
 */
class GetPackagesTest extends WP_UnitTestCase {

	public function tear_down() {
		$this->cleanup( 'fair-pkg-test-plugin' );
		$this->cleanup( 'fair-pkg-test-plugin2' );
		parent::tear_down();
	}

	/**
	 * Test should find a plugin with a Plugin ID header.
	 */
	public function test_should_find_plugin_with_plugin_id_header() {
		$this->create_plugin( 'fair-pkg-test-plugin', 'did:plc:z72i7hdynmk6r22z27h6tvur' );

		$packages = get_packages();

		$this->assertArrayHasKey( 'did:plc:z72i7hdynmk6r22z27h6tvur', $packages['plugins'] );
	}

	/**
	 * Test should find multiple plugins with different DIDs.
	 */
	public function test_should_find_multiple_plugins() {
		$this->create_plugin( 'fair-pkg-test-plugin', 'did:plc:aaa' );
		$this->create_plugin( 'fair-pkg-test-plugin2', 'did:plc:bbb', 'fair-pkg-test-plugin2/fair-pkg-test-plugin2.php' );

		$packages = get_packages();

		$this->assertArrayHasKey( 'did:plc:aaa', $packages['plugins'] );
		$this->assertArrayHasKey( 'did:plc:bbb', $packages['plugins'] );
	}

	/**
	 * Test should not find plugins without Plugin ID header.
	 */
	public function test_should_not_find_plugin_without_plugin_id() {
		$dir  = WP_PLUGIN_DIR . '/fair-pkg-test-plugin';
		$file = $dir . '/fair-pkg-test-plugin.php';
		wp_mkdir_p( $dir );
		file_put_contents( $file, "<?php\n/**\n * Plugin Name: No FAIR Plugin\n * Version: 1.0.0\n */" );

		$packages = get_packages();

		$this->assertArrayNotHasKey( 'plugins', $packages );
	}

	/**
	 * Test should have plugins key when FAIR packages are present.
	 */
	public function test_should_have_plugins_key_when_packages_present() {
		$this->create_plugin( 'fair-pkg-test-plugin', 'did:plc:z72i7hdynmk6r22z27h6tvur' );

		$packages = get_packages();

		$this->assertArrayHasKey( 'plugins', $packages );
		$this->assertArrayHasKey( "plugins", $packages );
	}

	private function create_plugin( string $dir_name, string $did, ?string $rel_path = null ): string {
		$rel_path = $rel_path ?? "{$dir_name}/{$dir_name}.php";
		$file     = WP_PLUGIN_DIR . '/' . $rel_path;
		wp_mkdir_p( dirname( $file ) );
		file_put_contents( $file, "<?php\n/**\n * Plugin Name: FAIR Test Plugin\n * Plugin ID: {$did}\n * Version: 1.0.0\n */" );
		return $file;
	}

	private function cleanup( string $dir_name ): void {
		$dir = WP_PLUGIN_DIR . '/' . $dir_name;
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$iter = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iter as $f ) {
			$f->isDir() ? rmdir( $f->getPathname() ) : unlink( $f->getPathname() );
		}
		rmdir( $dir );
	}
}
