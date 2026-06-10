<?php
/**
 * Tests for FAIR\Packages\search_by_did().
 *
 * @package FAIR
 */

use function FAIR\Packages\search_by_did;
use const FAIR\Packages\CACHE_METADATA_DOCUMENTS;
use const FAIR\Packages\CACHE_UPDATE_ERRORS;

/**
 * Tests for FAIR\Packages\search_by_did().
 *
 * @covers FAIR\Packages\search_by_did
 */
class SearchByDidTest extends WP_UnitTestCase {

	private string $test_did = 'did:plc:z72i7hdynmk6r22z27h6tvur';

	public function tear_down() {
		delete_site_transient( CACHE_METADATA_DOCUMENTS . $this->test_did );
		delete_site_transient( CACHE_UPDATE_ERRORS . $this->test_did );
		delete_site_transient( 'fair_did_error_' . $this->test_did );
		remove_all_filters( 'pre_http_request' );
		parent::tear_down();
	}

	/**
	 * Test should return original result when action is not query_plugins.
	 */
	public function test_should_return_result_for_non_plugin_action() {
		$result = 'original';

		$actual = search_by_did( $result, 'some_other_action', (object) [ 'search' => $this->test_did ] );

		$this->assertSame( $result, $actual, 'Should return original for non-plugin action.' );
	}

	/**
	 * Test should return original result when search is empty.
	 */
	public function test_should_return_result_for_empty_search() {
		$result = 'original';

		$actual = search_by_did( $result, 'query_plugins', (object) [ 'search' => '' ] );

		$this->assertSame( $result, $actual, 'Should return original for empty search.' );
	}

	/**
	 * Test should return original result when search is not a DID.
	 */
	public function test_should_return_result_for_non_did_search() {
		$result = [ 'plugins' => [] ];

		$actual = search_by_did( $result, 'query_plugins', (object) [ 'search' => 'my-plugin' ] );

		$this->assertSame( $result, $actual, 'Should return original for non-DID search.' );
	}

	/**
	 * Test should return original result when DID is wrong length.
	 */
	public function test_should_return_result_for_wrong_length_did() {
		$result = [ 'plugins' => [] ];

		$actual = search_by_did(
			$result,
			'query_plugins',
			(object) [ 'search' => 'did:plc:short' ]
		);

		$this->assertSame( $result, $actual, 'Should return original for too-short DID.' );
	}

	/**
	 * Test should return original result when pipeline fails.
	 */
	public function test_should_return_result_when_pipeline_fails() {
		// No DID document seeded → fetch failure.
		$result = [ 'plugins' => [] ];

		$actual = search_by_did(
			$result,
			'query_plugins',
			(object) [ 'search' => urlencode( $this->test_did ) ]
		);

		$this->assertSame( $result, $actual, 'Should return original when pipeline fails.' );
	}
}
