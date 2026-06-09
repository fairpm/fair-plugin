<?php
/**
 * Tests for FAIR\Packages\validate_package_alias() and fetch_and_validate_package_alias().
 *
 * @package FAIR
 */

use function FAIR\Packages\validate_package_alias;
use function FAIR\Packages\fetch_and_validate_package_alias;

/**
 * Tests for FAIR\Packages\validate_package_alias().
 *
 * @covers FAIR\Packages\validate_package_alias
 */
class ValidatePackageAliasTest extends WP_UnitTestCase {

	/**
	 * Tear down: clear any test transients.
	 */
	public function tear_down() {
		delete_site_transient( 'fair_did_alias_did:plc:z72i7hdynmk6r22z27h6tvur' );
		parent::tear_down();
	}

	/**
	 * Test should return cached value when available.
	 */
	public function test_should_return_cached_value() {
		set_site_transient(
			'fair_did_alias_did:plc:z72i7hdynmk6r22z27h6tvur',
			'cached.example.com',
			HOUR_IN_SECONDS
		);

		$did_doc = [ 'id' => 'did:plc:z72i7hdynmk6r22z27h6tvur' ];
		$actual  = validate_package_alias( $did_doc );

		$this->assertSame( 'cached.example.com', $actual, 'Cached value should be returned.' );
	}

	/**
	 * Test should cache the result after a fetch.
	 */
	public function test_should_cache_result() {
		// A DID doc with no aliases → null result.
		$did_doc = [ 'id' => 'did:plc:test-cache' ];

		$result = validate_package_alias( $did_doc );

		$cached = get_site_transient( 'fair_did_alias_did:plc:test-cache' );
		$this->assertSame( '', $cached, 'null result cached as empty string in WP transients.' );
	}

	/**
	 * Test should use unique cache key per DID.
	 */
	public function test_should_use_unique_cache_key() {
		set_site_transient( 'fair_did_alias_did:plc:aaa', 'example-a.com', HOUR_IN_SECONDS );
		set_site_transient( 'fair_did_alias_did:plc:bbb', 'example-b.com', HOUR_IN_SECONDS );

		$result_a = validate_package_alias( [ 'id' => 'did:plc:aaa' ] );
		$result_b = validate_package_alias( [ 'id' => 'did:plc:bbb' ] );

		$this->assertSame( 'example-a.com', $result_a, 'DID aaa should get its own cached value.' );
		$this->assertSame( 'example-b.com', $result_b, 'DID bbb should get its own cached value.' );
	}
}

/**
 * Tests for FAIR\Packages\fetch_and_validate_package_alias().
 *
 * @covers FAIR\Packages\fetch_and_validate_package_alias
 */
class FetchAndValidatePackageAliasTest extends WP_UnitTestCase {

	/**
	 * Test should return null when no alsoKnownAs aliases are present.
	 */
	public function test_should_return_null_when_no_aliases() {
		$did_doc = [ 'id' => 'did:plc:test' ];

		$actual = fetch_and_validate_package_alias( $did_doc );

		$this->assertNull( $actual, 'No aliases should return null.' );
	}

	/**
	 * Test should return null when aliases exist but none start with fair://.
	 */
	public function test_should_return_null_when_no_fair_aliases() {
		$did_doc = [
			'id'           => 'did:plc:test',
			'alsoKnownAs'  => [ 'https://example.com', 'at://handle' ],
		];

		$actual = fetch_and_validate_package_alias( $did_doc );

		$this->assertNull( $actual, 'Non-fair:// aliases should be ignored.' );
	}

	/**
	 * Test should return WP_Error when multiple fair:// aliases exist.
	 */
	public function test_should_return_error_for_multiple_aliases() {
		$did_doc = [
			'id'           => 'did:plc:test',
			'alsoKnownAs'  => [
				'fair://example.com/',
				'fair://other.com/',
			],
		];

		$actual = fetch_and_validate_package_alias( $did_doc );

		$this->assertWPError( $actual, 'Multiple aliases should return WP_Error.' );
		$this->assertSame(
			'fair.packages.get_package_alias.too_many_aliases',
			$actual->get_error_code(),
			'Error code should indicate too many aliases.'
		);
	}

	/**
	 * Test should return WP_Error for invalid domain format.
	 */
	public function test_should_return_error_for_invalid_domain_format() {
		$did_doc = [
			'id'           => 'did:plc:test',
			'alsoKnownAs'  => [ 'fair://!!invalid!!.com/' ],
		];

		$actual = fetch_and_validate_package_alias( $did_doc );

		$this->assertWPError( $actual, 'Invalid domain should return WP_Error.' );
		$this->assertSame(
			'fair.packages.get_package_alias.invalid_domain',
			$actual->get_error_code(),
			'Error code should indicate invalid domain.'
		);
	}

	/**
	 * Test should return WP_Error for domain with no TLD.
	 */
	public function test_should_return_error_for_domain_without_tld() {
		$did_doc = [
			'id'           => 'did:plc:test',
			'alsoKnownAs'  => [ 'fair://no-tld/' ],
		];

		$actual = fetch_and_validate_package_alias( $did_doc );

		$this->assertWPError( $actual, 'Domain without TLD should return WP_Error.' );
	}

	/**
	 * Test should return WP_Error for domain exceeding 255 characters.
	 */
	public function test_should_return_error_for_excessively_long_domain() {
		$long_domain = 'fair://' . str_repeat( 'a', 63 ) . '.' . str_repeat( 'b', 63 ) . '.' . str_repeat( 'c', 63 ) . '.' . str_repeat( 'd', 63 ) . '.com/';

		$did_doc = [
			'id'           => 'did:plc:test',
			'alsoKnownAs'  => [ $long_domain ],
		];

		$actual = fetch_and_validate_package_alias( $did_doc );

		$this->assertWPError( $actual, 'Excessively long domain should return WP_Error.' );
		$this->assertSame(
			'fair.packages.get_package_alias.domain_too_long',
			$actual->get_error_code(),
			'Error code should indicate domain too long.'
		);
	}

	/**
	 * Test should return WP_Error when alsoKnownAs is missing from DID doc.
	 */
	public function test_should_return_null_when_also_known_as_missing() {
		$did_doc = [
			'id'              => 'did:plc:test',
			'verificationMethod' => [],
		];

		$actual = fetch_and_validate_package_alias( $did_doc );

		$this->assertNull( $actual, 'Missing alsoKnownAs should return null.' );
	}

	/**
	 * Test should handle aliases with non-string values in the array.
	 */
	public function test_should_skip_non_string_aliases() {
		$did_doc = [
			'id'           => 'did:plc:test',
			'alsoKnownAs'  => [
				12345,
				(object) [ 'url' => 'fair://example.com/' ],
				'fair://valid.example.com/',
			],
		];

		// Non-string values are filtered out. The single valid alias should
		// proceed to DNS validation (which will likely fail in test env).
		$actual = fetch_and_validate_package_alias( $did_doc );

		// Should not return "too many aliases" error.
		if ( is_wp_error( $actual ) ) {
			$this->assertNotSame(
				'fair.packages.get_package_alias.too_many_aliases',
				$actual->get_error_code(),
				'Non-string aliases should be filtered out.'
			);
		}
	}
}
