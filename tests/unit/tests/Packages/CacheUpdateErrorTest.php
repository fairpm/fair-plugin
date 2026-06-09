<?php
/**
 * Tests for FAIR\Packages\cache_update_error() and clear_update_error().
 *
 * @package FAIR
 */

use function FAIR\Packages\cache_update_error;
use function FAIR\Packages\clear_update_error;
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
class ClearUpdateErrorTest extends WP_UnitTestCase {

	/**
	 * Test should delete a previously cached error.
	 */
	public function test_should_clear_cached_error() {
		$did   = 'did:plc:test-clear-error';
		$error = new WP_Error( 'test_code', 'Test error message' );

		cache_update_error( $did, $error );
		$this->assertNotFalse( get_site_transient( CACHE_UPDATE_ERRORS . $did ), 'Error should be cached before clearing.' );

		clear_update_error( $did );

		$this->assertFalse( get_site_transient( CACHE_UPDATE_ERRORS . $did ), 'Error should be cleared.' );
	}

	/**
	 * Test should not error when clearing a non-existent error.
	 */
	public function test_should_be_idempotent() {
		clear_update_error( 'did:plc:nonexistent' );

		// No exception = pass.
		$this->assertTrue( true, 'Clearing non-existent error should not throw.' );
	}

	/**
	 * Test should clear specific DID without affecting others.
	 */
	public function test_should_not_affect_other_dids() {
		$error_a = new WP_Error( 'code_a', 'Message A' );
		$error_b = new WP_Error( 'code_b', 'Message B' );

		cache_update_error( 'did:plc:aaa', $error_a );
		cache_update_error( 'did:plc:bbb', $error_b );

		clear_update_error( 'did:plc:aaa' );

		$this->assertFalse( get_site_transient( CACHE_UPDATE_ERRORS . 'did:plc:aaa' ), 'DID aaa should be cleared.' );
		$this->assertNotFalse( get_site_transient( CACHE_UPDATE_ERRORS . 'did:plc:bbb' ), 'DID bbb should remain cached.' );
	}
}
