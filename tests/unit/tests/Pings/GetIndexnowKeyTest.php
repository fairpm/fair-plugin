<?php
/**
 * Tests for FAIR\Pings\get_indexnow_key().
 *
 * @package FAIR
 */

use function FAIR\Pings\get_indexnow_key;

/**
 * Tests for FAIR\Pings\get_indexnow_key().
 *
 * @covers FAIR\Pings\get_indexnow_key
 */
class GetIndexnowKeyTest extends WP_UnitTestCase {

	public function tear_down() {
		delete_option( 'fair_indexnow_key' );
		parent::tear_down();
	}

	/**
	 * Test should generate a key when none exists.
	 */
	public function test_should_generate_key_when_none_exists() {
		delete_option( 'fair_indexnow_key' );

		$key = get_indexnow_key();

		$this->assertNotEmpty( $key, 'Key should be generated.' );
		// wp_generate_password produces alphanumeric (a-z, 0-9), not pure hex.
		$this->assertMatchesRegularExpression( '/^[a-z0-9]+$/', $key, 'Key should be lowercase alphanumeric.' );
		$this->assertSame( $key, strtolower( $key ), 'Key should be lowercase.' );
	}

	/**
	 * Test should be at least 8 characters.
	 */
	public function test_should_be_at_least_8_characters() {
		delete_option( 'fair_indexnow_key' );

		$key = get_indexnow_key();

		$this->assertGreaterThanOrEqual( 8, strlen( $key ), 'Key should be at least 8 characters.' );
	}

	/**
	 * Test should be at most 128 characters.
	 */
	public function test_should_be_at_most_128_characters() {
		delete_option( 'fair_indexnow_key' );

		$key = get_indexnow_key();

		$this->assertLessThanOrEqual( 128, strlen( $key ), 'Key should be at most 128 characters.' );
	}

	/**
	 * Test should return existing key without regenerating.
	 */
	public function test_should_return_existing_key() {
		update_option( 'fair_indexnow_key', 'abc123def456' );

		$key = get_indexnow_key();

		$this->assertSame( 'abc123def456', $key, 'Existing key should be returned.' );
	}
}
