<?php
/**
 * Tests for Updater::should_run_on_current_page().
 *
 * @package FAIR
 */

use FAIR\Updater\Updater;

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
