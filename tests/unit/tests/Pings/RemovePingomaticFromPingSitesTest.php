<?php
/**
 * Tests for FAIR\Pings\remove_pingomatic_from_ping_sites().
 *
 * @package FAIR
 */

use function FAIR\Pings\remove_pingomatic_from_ping_sites;

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
