<?php
/**
 * Tests for FAIR\Updater\Updater::plugin_api_details().
 *
 * @package FAIR
 */

use FAIR\Updater\Updater;
use const FAIR\Packages\CACHE_METADATA_DOCUMENTS;
use const FAIR\Packages\CACHE_UPDATE_ERRORS;
use const FAIR\Packages\CACHE_KEY;

/**
 * Tests for FAIR\Updater\Updater::plugin_api_details().
 *
 * @covers FAIR\Updater\Updater::plugin_api_details
 */
class PluginApiDetailsTest extends WP_UnitTestCase {

	private string $test_did = 'did:plc:z72i7hdynmk6r22z27h6tvur';

	public function tear_down() {
		delete_site_transient( CACHE_METADATA_DOCUMENTS . $this->test_did );
		delete_site_transient( CACHE_UPDATE_ERRORS . $this->test_did );
		delete_site_transient( 'fair-metadata-endpoint-' . $this->test_did );
		delete_site_transient( CACHE_KEY . md5( 'https://test.example.com/metadata/' . $this->test_did ) );
		remove_all_filters( 'pre_http_request' );
		Updater::reset();
		$this->cleanup_plugin();
		parent::tear_down();
	}

	private function cleanup_plugin(): void {
		$dir = WP_PLUGIN_DIR . '/api-test-plugin';
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$iter = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iter as $f ) {
			$f->isDir() ? rmdir( $f->getPathname() ) : unlink( $f->getPathname() );
		}
		rmdir( $dir );
	}

	private function register_test_plugin(): void {
		$dir  = WP_PLUGIN_DIR . '/api-test-plugin';
		$file = $dir . '/api-test-plugin.php';
		wp_mkdir_p( $dir );
		file_put_contents( $file, "<?php\n/**\n * Plugin Name: API Test\n * Version: 1.0.0\n */" );
		Updater::register_plugin( $this->test_did, $file );
	}

	private function seed_pipeline(): void {
		set_site_transient( CACHE_METADATA_DOCUMENTS . $this->test_did, [
			'id'                 => $this->test_did,
			'alsoKnownAs'        => [],
			'verificationMethod' => [
				[
					'id'                 => '#fair-key',
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

		$test_did = $this->test_did;
		add_filter( 'pre_http_request', function () use ( $test_did ) {
			return [
				'body'     => json_encode( (object) [
					'id'       => $test_did,
					'name'     => 'API Test Plugin',
					'slug'     => 'api-test-plugin',
					'type'     => 'wp-plugin',
					'filename' => 'api-test-plugin/api-test-plugin.php',
					'license'  => 'GPL-2.0-only',
					'authors'  => [ (object) [ 'name' => 'Author', 'url' => 'https://example.com' ] ],
					'security' => [],
					'releases' => [
						(object) [
							'version'   => '2.0.0',
							'artifacts' => (object) [
								'package' => [
									(object) [ 'url' => 'https://example.com/release.zip' ],
								],
							],
						],
					],
				] ),
				'response' => [ 'code' => 200 ],
				'headers'  => [],
			];
		}, 10, 0 );
	}

	/**
	 * Test should return original result when action is not plugin_information.
	 */
	public function test_should_return_result_for_non_plugin_info_action(): void {
		$result = 'original';

		$actual = Updater::plugin_api_details( $result, 'some_other_action', (object) [ 'slug' => 'test' ] );

		$this->assertSame( $result, $actual, 'Non-plugin_information action should return original.' );
	}

	/**
	 * Test should return original result when slug is empty.
	 */
	public function test_should_return_result_for_empty_slug(): void {
		$result = 'original';

		$actual = Updater::plugin_api_details( $result, 'plugin_information', (object) [ 'slug' => '' ] );

		$this->assertSame( $result, $actual, 'Empty slug should return original.' );
	}

	/**
	 * Test should return original result when no plugin matches slug.
	 */
	public function test_should_return_result_when_slug_not_found(): void {
		$this->register_test_plugin();
		$result = false;

		$actual = Updater::plugin_api_details(
			$result,
			'plugin_information',
			(object) [ 'slug' => 'nonexistent-plugin' ]
		);

		$this->assertFalse( $actual, 'Unmatched slug should return original false.' );
	}

	/**
	 * Test should return plugin info object when pipeline succeeds.
	 */
	public function test_should_return_plugin_info_on_success(): void {
		$this->register_test_plugin();
		$this->seed_pipeline();

		// The slug includes the DID hash suffix (api-test-plugin-<6 char hash>).
		// find_package_by_api_slug checks both slug and slug-hash.
		$hash = \FAIR\Packages\get_did_hash( $this->test_did );

		$actual = Updater::plugin_api_details(
			false,
			'plugin_information',
			(object) [ 'slug' => 'api-test-plugin-' . $hash ]
		);

		$this->assertIsObject( $actual, 'Should return plugin info object.' );
		$this->assertSame( 'API Test Plugin', $actual->name, 'Plugin name should match.' );
		$this->assertSame( '2.0.0', $actual->new_version, 'Version should match latest release.' );
	}
}
