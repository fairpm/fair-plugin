<?php
/**
 * Tests for FAIR\Updater\Updater::customize_theme_update_html().
 *
 * Verifies that FAIR-registered themes get View Details / Update Now
 * links appended to their description on the themes admin page.
 *
 * Uses seeded transients to avoid network calls — treats the DID doc
 * and metadata cache as the integration point.
 *
 * @package FAIR
 */

declare( strict_types=1 );

namespace FAIR\Tests\Updater;

use FAIR\Updater\Updater;
use const FAIR\Packages\CACHE_METADATA_DOCUMENTS;

/**
 * Tests for Updater::customize_theme_update_html().
 *
 * @covers FAIR\Updater\Updater::customize_theme_update_html
 */
class CustomizeThemeUpdateHtmlTest extends \WP_UnitTestCase {

	private string $test_did = 'did:plc:theme-test-1234567890';

	protected function setUp(): void {
		parent::setUp();

		// Create a test theme directory with style.css and Theme ID header.
		$this->create_test_theme();

		// Seed a DID document so get_did_document() returns without network.
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

		// Mock the metadata HTTP response so fetch_package_metadata() succeeds.
		add_filter( 'pre_http_request', function () {
			return [
				'body'     => json_encode( (object) [
					'id'       => $this->test_did,
					'name'     => 'FAIR Test Theme',
					'slug'     => 'fair-test-theme',
					'filename' => 'fair-test-theme/style.css',
					'type'     => 'wp-theme',
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

	protected function tearDown(): void {
		$this->cleanup_theme();
		Updater::reset();
		delete_site_transient( CACHE_METADATA_DOCUMENTS . $this->test_did );
		delete_site_transient( 'fair-metadata-endpoint-' . $this->test_did );
		delete_site_transient( \FAIR\Packages\CACHE_KEY . md5(
			'https://test.example.com/metadata/' . $this->test_did
		) );
		delete_site_transient( 'update_themes' );
		remove_all_filters( 'pre_http_request' );
		parent::tearDown();
	}

	/**
	 * Test that a FAIR-registered theme with an update available gets
	 * update links appended to its description.
	 */
	public function test_should_append_fair_content_to_theme_description(): void {
		// Register the theme with the updater.
		$theme_file = get_theme_root() . '/fair-test-theme/style.css';
		Updater::register_theme( $this->test_did, $theme_file );

		// Seed update_themes transient so append_theme_actions_content
		// finds an update and produces output. Temporarily remove the
		// site_transient_update_themes filter to avoid triggering the
		// full update pipeline during test setup.
		remove_filter( 'site_transient_update_themes', [ Updater::class, 'handle_update_themes_transient' ], 20 );
		set_site_transient( 'update_themes', (object) [
			'response' => [
				'fair-test-theme' => [
					'package' => 'https://example.com/theme.zip',
				],
			],
		] );
		add_filter( 'site_transient_update_themes', [ Updater::class, 'handle_update_themes_transient' ], 20, 1 );

		// Simulate the prepared themes array that wp_prepare_themes_for_js produces.
		$prepared = [
			'fair-test-theme' => [
				'name'        => 'FAIR Test Theme',
				'description' => 'Original description.',
				'hasUpdate'   => false,
			],
		];

		$result = Updater::customize_theme_update_html( $prepared );

		$this->assertArrayHasKey( 'fair-test-theme', $result );
		$this->assertStringContainsString(
			'There is a new version of',
			$result['fair-test-theme']['description'],
			'Update notification should be appended to theme description.'
		);
		$this->assertStringContainsString(
			'update now',
			$result['fair-test-theme']['description'],
			'Update link should be present.'
		);
	}

	/**
	 * Test that themes not in the registry are left untouched.
	 */
	public function test_should_not_modify_unregistered_themes(): void {
		$prepared = [
			'unrelated-theme' => [
				'name'        => 'Unrelated Theme',
				'description' => 'Vanilla description.',
			],
		];

		$result = Updater::customize_theme_update_html( $prepared );

		$this->assertSame( 'Vanilla description.', $result['unrelated-theme']['description'] );
	}

	/**
	 * Test that an empty registry does not error.
	 */
	public function test_should_handle_empty_registry(): void {
		$prepared = [
			'some-theme' => [
				'name'        => 'Some Theme',
				'description' => 'Desc.',
			],
		];

		$result = Updater::customize_theme_update_html( $prepared );

		$this->assertSame( 'Desc.', $result['some-theme']['description'] );
	}

	private function create_test_theme(): void {
		$dir  = get_theme_root() . '/fair-test-theme';
		$file = $dir . '/style.css';

		if ( ! is_dir( $dir ) ) {
			mkdir( $dir, 0755, true );
		}

		file_put_contents( $file, "/*\nTheme Name: FAIR Test Theme\nTheme ID: {$this->test_did}\nVersion: 1.0.0\n*/" );
	}

	private function cleanup_theme(): void {
		$dir = get_theme_root() . '/fair-test-theme';
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
}
