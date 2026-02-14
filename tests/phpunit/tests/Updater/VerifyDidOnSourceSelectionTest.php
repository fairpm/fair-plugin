<?php
/**
 * Tests for FAIR\Updater\verify_did_on_source_selection().
 *
 * @package FAIR
 */

use const FAIR\Packages\CACHE_DID_FOR_INSTALL;
use function FAIR\Updater\verify_did_on_source_selection;

/**
 * Tests for FAIR\Updater\verify_did_on_source_selection().
 *
 * @covers FAIR\Updater\verify_did_on_source_selection
 */
class VerifyDidOnSourceSelectionTest extends WP_UnitTestCase {

	/**
	 * Fixtures directory.
	 *
	 * @var string
	 */
	private string $fixtures_dir;

	/**
	 * Set up each test.
	 */
	public function set_up() {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		WP_Filesystem();
		$this->fixtures_dir = dirname( __DIR__, 2 ) . '/data/updater';
	}

	/**
	 * Tests that WP_Error input is passed through unchanged.
	 */
	public function test_should_pass_through_wp_error() {
		$error    = new WP_Error( 'previous_error', 'Something failed' );
		$upgrader = $this->createMock( Plugin_Upgrader::class );

		$result = verify_did_on_source_selection( $error, '/tmp/remote', $upgrader, [] );

		$this->assertWPError( $result, 'WP_Error input should be returned unchanged.' );
		$this->assertSame( 'previous_error', $result->get_error_code(), 'Error code should be preserved.' );
	}

	/**
	 * Tests that source is returned when the plugin DID matches.
	 */
	public function test_should_return_source_when_plugin_did_matches() {
		$source = $this->fixtures_dir . '/test-plugin/';
		set_site_transient( CACHE_DID_FOR_INSTALL, 'did:plc:testmatchingtestmatchi00' );

		$upgrader = $this->createMock( Plugin_Upgrader::class );

		$result = verify_did_on_source_selection( $source, '/tmp/remote', $upgrader, [] );

		$this->assertSame( $source, $result, 'Source should be returned when plugin DID matches.' );
	}

	/**
	 * Tests that source is returned when the theme DID matches.
	 */
	public function test_should_return_source_when_theme_did_matches() {
		$source = $this->fixtures_dir . '/test-theme/';
		set_site_transient( CACHE_DID_FOR_INSTALL, 'did:plc:testmatchingtestmatchi00' );

		$upgrader = $this->createMock( Theme_Upgrader::class );

		$result = verify_did_on_source_selection( $source, '/tmp/remote', $upgrader, [] );

		$this->assertSame( $source, $result, 'Source should be returned when theme DID matches.' );
	}

	/**
	 * Tests that a WP_Error is returned when the plugin DID does not match.
	 */
	public function test_should_return_error_when_plugin_did_mismatches() {
		$source = $this->fixtures_dir . '/test-plugin/';
		set_site_transient( CACHE_DID_FOR_INSTALL, 'did:plc:wrongdidwrongdidwrongd00' );

		$upgrader = $this->createMock( Plugin_Upgrader::class );

		$result = verify_did_on_source_selection( $source, '/tmp/remote', $upgrader, [] );

		$this->assertWPError( $result, 'A WP_Error should be returned when the plugin DID does not match.' );
		$this->assertSame(
			'fair.packages.did_verification.mismatch',
			$result->get_error_code(),
			'Error code should indicate a DID mismatch.'
		);
	}

	/**
	 * Tests that a WP_Error is returned when the theme DID does not match.
	 */
	public function test_should_return_error_when_theme_did_mismatches() {
		$source = $this->fixtures_dir . '/test-theme/';
		set_site_transient( CACHE_DID_FOR_INSTALL, 'did:plc:wrongdidwrongdidwrongd00' );

		$upgrader = $this->createMock( Theme_Upgrader::class );

		$result = verify_did_on_source_selection( $source, '/tmp/remote', $upgrader, [] );

		$this->assertWPError( $result, 'A WP_Error should be returned when the theme DID does not match.' );
		$this->assertSame(
			'fair.packages.did_verification.mismatch',
			$result->get_error_code(),
			'Error code should indicate a DID mismatch.'
		);
	}

	/**
	 * Tests that a WP_Error is returned when the plugin has no Plugin ID header.
	 */
	public function test_should_return_error_when_plugin_has_no_did() {
		$source = $this->fixtures_dir . '/test-plugin-no-did/';
		set_site_transient( CACHE_DID_FOR_INSTALL, 'did:plc:testmatchingtestmatchi00' );

		$upgrader = $this->createMock( Plugin_Upgrader::class );

		$result = verify_did_on_source_selection( $source, '/tmp/remote', $upgrader, [] );

		$this->assertWPError( $result, 'A WP_Error should be returned when the plugin has no DID.' );
		$this->assertSame(
			'fair.packages.did_verification.not_found',
			$result->get_error_code(),
			'Error code should indicate a missing DID.'
		);
	}

	/**
	 * Tests that a WP_Error is returned when the theme has no Theme ID header.
	 */
	public function test_should_return_error_when_theme_has_no_did() {
		$source = $this->fixtures_dir . '/test-theme-no-did/';
		set_site_transient( CACHE_DID_FOR_INSTALL, 'did:plc:testmatchingtestmatchi00' );

		$upgrader = $this->createMock( Theme_Upgrader::class );

		$result = verify_did_on_source_selection( $source, '/tmp/remote', $upgrader, [] );

		$this->assertWPError( $result, 'A WP_Error should be returned when the theme has no DID.' );
		$this->assertSame(
			'fair.packages.did_verification.not_found',
			$result->get_error_code(),
			'Error code should indicate a missing DID.'
		);
	}
}
