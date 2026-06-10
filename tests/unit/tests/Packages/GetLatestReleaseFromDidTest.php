<?php
/**
 * Tests for FAIR\Packages\get_latest_release_from_did().
 *
 * @package FAIR
 */

use function FAIR\Packages\get_latest_release_from_did;
use const FAIR\Packages\CACHE_METADATA_DOCUMENTS;
use const FAIR\Packages\CACHE_UPDATE_ERRORS;

/**
 * Tests for FAIR\Packages\get_latest_release_from_did().
 *
 * @covers FAIR\Packages\get_latest_release_from_did
 */
class GetLatestReleaseFromDidTest extends WP_UnitTestCase {

	private string $test_did = 'did:plc:z72i7hdynmk6r22z27h6tvur';
	private string $cache_key;

	public function set_up() {
		parent::set_up();
		$this->cache_key = CACHE_METADATA_DOCUMENTS . $this->test_did;
	}

	public function tear_down() {
		delete_site_transient( $this->cache_key );
		delete_site_transient( CACHE_UPDATE_ERRORS . $this->test_did );
		delete_site_transient( 'fair_did_error_' . $this->test_did );
		remove_all_filters( 'pre_http_request' );
		parent::tear_down();
	}

	private function seed_full_pipeline(): void {
		// Seed DID document with service and keys.
		set_site_transient( $this->cache_key, [
			'id'                 => $this->test_did,
			'alsoKnownAs'        => [],
			'verificationMethod' => [
				[
					'id'                 => '#fair-key-1',
					'type'               => 'Multikey',
					'publicKeyMultibase'  => 'z6MkhaXgBXDvQtf5VgJKe',
				],
			],
			'service'            => [
				[
					'type'            => 'FairPackageManagementRepo',
					'serviceEndpoint' => 'https://test.example.com/metadata/' . $this->test_did,
				],
			],
		], HOUR_IN_SECONDS );

		add_filter( 'pre_http_request', function () {
			return [
				'body'     => json_encode( (object) [
					'id'       => $this->test_did,
					'type'     => 'wp-plugin',
					'license'  => 'GPL-2.0-only',
					'authors'  => [ (object) [ 'name' => 'Test', 'url' => 'https://example.com' ] ],
					'security' => [],
					'releases' => [
						(object) [
							'version'   => '2.0.0',
							'artifacts' => (object) [ 'package' => [] ],
						],
						(object) [
							'version'   => '1.0.0',
							'artifacts' => (object) [ 'package' => [] ],
						],
					],
				] ),
				'response' => [ 'code' => 200 ],
				'headers'  => [],
			];
		}, 10, 0 );
	}

	/**
	 * Test should return latest release when pipeline succeeds.
	 */
	public function test_should_return_latest_release() {
		$this->seed_full_pipeline();

		$actual = get_latest_release_from_did( $this->test_did );

		$this->assertInstanceOf(
			\FAIR\Packages\ReleaseDocument::class,
			$actual,
			'Should return ReleaseDocument.'
		);
		$this->assertSame( '2.0.0', $actual->version, 'Should return latest version.' );
	}

	/**
	 * Test should return WP_Error when no signing keys exist.
	 */
	public function test_should_return_error_when_no_signing_keys() {
		set_site_transient( $this->cache_key, [
			'id'                 => $this->test_did,
			'alsoKnownAs'        => [],
			'verificationMethod' => [], // No keys
			'service'            => [],
		], HOUR_IN_SECONDS );

		$actual = get_latest_release_from_did( $this->test_did );

		$this->assertWPError( $actual, 'No signing keys should return WP_Error.' );
		$this->assertSame(
			'fair.packages.install.no_signing_keys',
			$actual->get_error_code(),
			'Error code should indicate no signing keys.'
		);
	}
}
