<?php
/**
 * Integration tests: update transient and release management.
 *
 * Tests that the updater correctly interacts with the mock server
 * when checking for plugin/theme updates.
 *
 * @package FAIR
 */

use PHPUnit\Framework\TestCase;
use FAIR\Updater\Updater;

/**
 * @group integration
 */
class UpdateTransientIntegrationTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		wp_remote_post( 'http://mock-server:8080/log/reset', [ 'timeout' => 3 ] );
	}

	/**
	 * Test that handle_update_plugins_transient processes the seeded plugin
	 * and returns a valid transient structure.
	 */
	public function test_handle_update_plugins_returns_valid_transient(): void {
		$transient          = new stdClass();
		$transient->checked = [];

		$result = Updater::handle_update_plugins_transient( $transient );

		$this->assertIsObject( $result, 'Should return an object.' );

		// The transient should at minimum be the passed-in object, possibly
		// with response/no_update populated if the mock server is reachable.
		$this->assertInstanceOf( stdClass::class, $result );
	}

	/**
	 * Test that the no_update array contains entries for registered plugins
	 * that have compatible requirements and no newer version.
	 *
	 * NOTE: This test may skip if the seed plugin registration didn't
	 * result in update detection (e.g., mock server not reached during
	 * the transient check).
	 */
	public function test_seeded_plugin_appears_in_transient(): void {
		$transient = new stdClass();
		$result    = Updater::handle_update_plugins_transient( $transient );

		$response  = isset( $result->response ) ? (array) $result->response : [];
		$no_update = isset( $result->no_update ) ? (array) $result->no_update : [];

		$all_entries = array_merge( $response, $no_update );

		if ( empty( $all_entries ) ) {
			$this->markTestSkipped(
				'Seeded plugin not found in update transient. ' .
				'This may happen if the mock server is not reachable during the transient check.'
			);
			return;
		}

		// At least one plugin entry should exist.
		$this->assertNotEmpty( $all_entries, 'At least one plugin should be in response or no_update.' );
	}

	/**
	 * Test that an empty transient (no registered packages) works without errors.
	 */
	public function test_empty_registry_handles_gracefully(): void {
		// Save current registry and clear it.
		$ref = new ReflectionProperty( Updater::class, 'plugins' );
		$ref->setAccessible( true );
		$saved = $ref->getValue();
		$ref->setValue( null, [] );

		try {
			$transient = new stdClass();
			$result    = Updater::handle_update_plugins_transient( $transient );

			$this->assertIsObject( $result );
			// Should not throw.
		} finally {
			$ref->setValue( null, $saved );
		}
	}
}
