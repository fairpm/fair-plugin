<?php
/**
 * Tests for FAIR\Packages\cache_update_error().
 *
 * @package FAIR
 */

use function FAIR\Packages\cache_update_error;
use const FAIR\Packages\CACHE_UPDATE_ERRORS;

/**
 * Tests for FAIR\Packages\cache_update_error().
 *
 * @covers FAIR\Packages\cache_update_error
 */
class CacheUpdateErrorTest extends WP_UnitTestCase {

	/**
	 * Test should store a WP_Error in the site transient.
	 */
	public function test_should_cache_error() {
		$did   = 'did:plc:test-cache-error';
		$error = new WP_Error( 'test_code', 'Test error message' );

		cache_update_error( $did, $error );

		$cached = get_site_transient( CACHE_UPDATE_ERRORS . $did );
		$this->assertInstanceOf( WP_Error::class, $cached, 'Cached value should be a WP_Error.' );
		$this->assertSame( 'test_code', $cached->get_error_code(), 'Error code should match.' );
	}

	/**
	 * Test should attach a timestamp to the error data.
	 */
	public function test_should_add_timestamp_to_error_data() {
		$did   = 'did:plc:test-cache-error';
		$error = new WP_Error( 'test_code', 'Test error message' );

		$before = time();
		cache_update_error( $did, $error );
		$after = time();

		$cached = get_site_transient( CACHE_UPDATE_ERRORS . $did );
		$data   = $cached->get_error_data();

		$this->assertIsArray( $data, 'Error data should be stored.' );
		$this->assertArrayHasKey( 'timestamp', $data, 'Data should include timestamp.' );
		$this->assertGreaterThanOrEqual( $before, $data['timestamp'], 'Timestamp should be >= before.' );
		$this->assertLessThanOrEqual( $after, $data['timestamp'], 'Timestamp should be <= after.' );
	}

	/**
	 * Test should set a transient with failure cache lifetime.
	 */
	public function test_should_set_transient_with_lifetime() {
		$did   = 'did:plc:test-cache-error';
		$error = new WP_Error( 'test_code', 'Test error message' );

		cache_update_error( $did, $error );

		$cached = get_site_transient( CACHE_UPDATE_ERRORS . $did );
		$this->assertNotFalse( $cached, 'Transient should be set.' );
	}
}

/**
 * Tests for FAIR\Packages\clear_update_error().
 *
 * @covers FAIR\Packages\clear_update_error
 */
