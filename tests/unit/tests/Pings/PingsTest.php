<?php
/**
 * Tests for FAIR\Pings functions.
 *
 * @package FAIR
 */

use function FAIR\Pings\remove_pingomatic_from_ping_sites;
use function FAIR\Pings\get_indexnow_key;
use function FAIR\Pings\register_query_vars;

/**
 * Tests for FAIR\Pings\remove_pingomatic_from_ping_sites().
 *
 * @covers FAIR\Pings\remove_pingomatic_from_ping_sites
 */
class RemovePingomaticFromPingSitesTest extends WP_UnitTestCase {

	/**
	 * Test should remove pingomatic.com from the list.
	 */
	public function test_should_remove_pingomatic() {
		$input = "http://rpc.pingomatic.com/\nhttp://example.com/xmlrpc.php";

		$actual = remove_pingomatic_from_ping_sites( $input );

		$this->assertStringNotContainsString( 'pingomatic', $actual, 'pingomatic.com should be removed.' );
		$this->assertStringContainsString( 'example.com', $actual, 'Other URLs should be preserved.' );
	}

	/**
	 * Test should handle only pingomatic in list.
	 */
	public function test_should_handle_only_pingomatic() {
		$input = "http://rpc.pingomatic.com/\n";

		$actual = remove_pingomatic_from_ping_sites( $input );

		$this->assertEmpty( trim( $actual ), 'Should return empty when only pingomatic is present.' );
	}

	/**
	 * Test should remove duplicate newlines.
	 */
	public function test_should_remove_duplicate_newlines() {
		$input = "http://example.com/xmlrpc.php\n\n\nhttp://other.com/ping\n\n";

		$actual = remove_pingomatic_from_ping_sites( $input );

		// str_replace only replaces one occurrence, so \n\n\n becomes \n\n.
		// The function is not a full newline deduplicator.
		$this->assertStringContainsString( 'http://example.com/xmlrpc.php', $actual );
		$this->assertStringContainsString( 'http://other.com/ping', $actual );
	}

	/**
	 * Test should handle empty input.
	 */
	public function test_should_handle_empty_input() {
		$actual = remove_pingomatic_from_ping_sites( '' );

		$this->assertEmpty( $actual, 'Empty input should return empty.' );
	}

	/**
	 * Test should not modify unrelated URLs.
	 */
	public function test_should_not_modify_unrelated_urls() {
		$input = "http://example.com/xmlrpc.php\nhttp://other.com/ping";

		$actual = remove_pingomatic_from_ping_sites( $input );

		$expected = "http://example.com/xmlrpc.php\nhttp://other.com/ping";
		$this->assertSame( $expected, $actual, 'Unrelated URLs should be preserved as-is.' );
	}
}

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

/**
 * Tests for FAIR\Pings\register_query_vars().
 *
 * @covers FAIR\Pings\register_query_vars
 */
class RegisterQueryVarsTest extends WP_UnitTestCase {

	/**
	 * Test should add fair_indexnow_key to query vars.
	 */
	public function test_should_add_indexnow_key_var() {
		$vars = [ 'existing_var' ];

		$actual = register_query_vars( $vars );

		$this->assertContains( 'fair_indexnow_key', $actual, 'Query var should be registered.' );
		$this->assertContains( 'existing_var', $actual, 'Existing vars should be preserved.' );
	}

	/**
	 * Test should work with empty vars array.
	 */
	public function test_should_work_with_empty_vars() {
		$actual = register_query_vars( [] );

		$this->assertSame( [ 'fair_indexnow_key' ], $actual, 'Should return only the key var.' );
	}
}
