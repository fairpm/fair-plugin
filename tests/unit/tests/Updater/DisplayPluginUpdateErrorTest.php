<?php
/**
 * Tests for FAIR\Updater\display_plugin_update_error().
 *
 * @package FAIR
 */

use function FAIR\Updater\display_plugin_update_error;
use const FAIR\Packages\CACHE_UPDATE_ERRORS;
use const FAIR\CACHE_LIFETIME_FAILURE;

/**
 * Tests for FAIR\Updater\display_plugin_update_error().
 *
 * @covers FAIR\Updater\display_plugin_update_error
 */
class DisplayPluginUpdateErrorTest extends WP_UnitTestCase {

	private string $test_did = 'did:plc:z72i7hdynmk6r22z27h6tvur';

	public function tear_down() {
		delete_site_transient( CACHE_UPDATE_ERRORS . $this->test_did );
		parent::tear_down();
	}

	/**
	 * Test should output nothing when no cached error exists.
	 */
	public function test_should_output_nothing_when_no_error() {
		delete_site_transient( CACHE_UPDATE_ERRORS . $this->test_did );

		ob_start();
		display_plugin_update_error( 'test/test.php', [], 'all', $this->test_did );
		$output = ob_get_clean();

		$this->assertEmpty( $output, 'Should output nothing when no error is cached.' );
	}

	/**
	 * Test should output nothing when transient exists but is not WP_Error.
	 */
	public function test_should_output_nothing_when_transient_not_error() {
		set_site_transient( CACHE_UPDATE_ERRORS . $this->test_did, 'some string', HOUR_IN_SECONDS );

		ob_start();
		display_plugin_update_error( 'test/test.php', [], 'all', $this->test_did );
		$output = ob_get_clean();

		$this->assertEmpty( $output, 'Should output nothing when transient is not a WP_Error.' );
	}

	/**
	 * Test should output error row when cached error exists.
	 */
	public function test_should_output_error_row_when_error_cached() {
		$error = new WP_Error(
			'fair.packages.did.fetch_error',
			'Test error message for display.'
		);
		$error->add_data( [ 'timestamp' => time() - 60 ], 'fair.packages.did.fetch_error' );
		set_site_transient( CACHE_UPDATE_ERRORS . $this->test_did, $error, HOUR_IN_SECONDS );

		ob_start();
		display_plugin_update_error( 'test-plugin/test-plugin.php', [], 'all', $this->test_did );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'plugin-update-tr', $output, 'Should output update row markup.' );
		$this->assertStringContainsString( 'Test error message for display.', $output, 'Should include error message.' );
		$this->assertStringContainsString( 'notice-error', $output, 'Should include error notice class.' );
		$this->assertStringContainsString( 'Update checks paused', $output, 'Should include retry message.' );
	}

	/**
	 * Test should include active class when plugin is active.
	 */
	public function test_should_include_active_class_for_active_plugin() {
		// Create and activate a test plugin.
		$plugin_file = $this->create_test_plugin();

		$error = new WP_Error( 'test_code', 'Test message' );
		$error->add_data( [ 'timestamp' => time() - 60 ], 'test_code' );
		set_site_transient( CACHE_UPDATE_ERRORS . $this->test_did, $error, HOUR_IN_SECONDS );

		$rel_path = plugin_basename( $plugin_file );
		activate_plugin( $rel_path );

		ob_start();
		display_plugin_update_error( $rel_path, [], 'all', $this->test_did );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'active', $output, 'Should include active class for active plugin.' );

		deactivate_plugins( $rel_path );
		$this->cleanup_test_plugin( $plugin_file );
	}

	/**
	 * Test should sanitize HTML in the error message.
	 */
	public function test_should_sanitize_html_in_error_message() {
		$error = new WP_Error( 'test_code', '<script>alert("xss")</script>' );
		$error->add_data( [ 'timestamp' => time() - 60 ], 'test_code' );
		set_site_transient( CACHE_UPDATE_ERRORS . $this->test_did, $error, HOUR_IN_SECONDS );

		ob_start();
		display_plugin_update_error( 'test/test.php', [], 'all', $this->test_did );
		$output = ob_get_clean();

		$this->assertStringNotContainsString( '<script>', $output, 'Script tags should be escaped.' );
		$this->assertStringContainsString( '&lt;script&gt;', $output, 'Script tags should be HTML-escaped.' );
	}

	/**
	 * Test should include col span from list table.
	 */
	public function test_should_include_colspan() {
		$error = new WP_Error( 'test_code', 'Colspan test' );
		$error->add_data( [ 'timestamp' => time() - 60 ], 'test_code' );
		set_site_transient( CACHE_UPDATE_ERRORS . $this->test_did, $error, HOUR_IN_SECONDS );

		ob_start();
		display_plugin_update_error( 'test/test.php', [], 'all', $this->test_did );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'colspan="', $output, 'Should include colspan attribute.' );
	}

	// ── Helpers ────────────────────────────────────────────────────

	private function create_test_plugin(): string {
		$dir  = WP_PLUGIN_DIR . '/fair-error-test-plugin';
		$file = $dir . '/fair-error-test-plugin.php';
		wp_mkdir_p( $dir );
		file_put_contents( $file, "<?php\n/**\n * Plugin Name: Error Test\n * Version: 1.0.0\n */" );
		return $file;
	}

	private function cleanup_test_plugin( string $file ): void {
		$dir = dirname( $file );
		if ( is_dir( $dir ) ) {
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
}
