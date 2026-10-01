<?php
/**
 * Tests for FAIR\Packages\maybe_rename_on_package_download().
 *
 * @package FAIR
 */

use function FAIR\Packages\get_did_hash;
use function FAIR\Packages\maybe_rename_on_package_download;

/**
 * Tests for FAIR\Packages\maybe_rename_on_package_download().
 *
 * All package data is served from seeded transients so that no outbound
 * HTTP requests are made.
 *
 * @covers FAIR\Packages\maybe_rename_on_package_download
 */
class MaybeRenameOnPackageDownloadTest extends WP_UnitTestCase {

	/**
	 * DID used for the seeded package.
	 *
	 * @var string
	 */
	private string $did = 'did:plc:example1234567890123456789';

	/**
	 * Metadata endpoint URL used for the seeded package.
	 *
	 * Must not contain home_url() so the local metadata path is not used.
	 *
	 * @var string
	 */
	private string $metadata_url = 'https://packages.example.test/fair-metadata.json';

	/**
	 * Base directory for the fake upgrade sources.
	 *
	 * @var string
	 */
	private string $base_dir;

	/**
	 * Seed the package metadata chain and prepare the filesystem.
	 */
	public function set_up() {
		parent::set_up();

		$this->seed_package_metadata();

		$this->base_dir = trailingslashit( get_temp_dir() ) . uniqid( 'fair-rename-test-' );
		wp_mkdir_p( $this->base_dir );

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		$this->assertTrue( WP_Filesystem(), 'Tests require a working WP_Filesystem.' );
	}

	/**
	 * Clean up transients and temporary directories.
	 */
	public function tear_down() {
		global $wp_filesystem;

		delete_site_transient( \FAIR\Packages\CACHE_DID_FOR_INSTALL );
		delete_site_transient( \FAIR\Packages\CACHE_METADATA_DOCUMENTS . $this->did );
		delete_site_transient( \FAIR\Packages\CACHE_UPDATE_ERRORS . $this->did );
		delete_site_transient( \FAIR\Packages\CACHE_KEY . md5( $this->metadata_url ) );

		if ( ! empty( $this->base_dir ) && $wp_filesystem instanceof WP_Filesystem_Base ) {
			$wp_filesystem->delete( $this->base_dir, true );
		}

		parent::tear_down();
	}

	/**
	 * Test that an update never renames the source directory.
	 *
	 * Core derives the install destination, the old package deletion, and the
	 * active_plugins entry from hook_extra['plugin'], so a renamed source would
	 * orphan the active plugin.
	 */
	public function test_should_not_rename_during_plugin_update() {
		$source = $this->create_source( 'fair-plugin-1.4.1' );
		$remote_source = dirname( untrailingslashit( $source ) );
		$upgrader = $this->get_plugin_upgrader();

		$result = maybe_rename_on_package_download(
			$source,
			$remote_source,
			$upgrader,
			[
				'action' => 'update',
				'type' => 'plugin',
				'plugin' => 'fair-plugin/plugin.php',
			]
		);

		$this->assertSame( $source, $result, 'The source path must not change during an update.' );
		$this->assertDirectoryExists( untrailingslashit( $source ), 'The original source directory must be left in place.' );
		$this->assertDirectoryDoesNotExist( $remote_source . '/fair-plugin-' . get_did_hash( $this->did ), 'No DID-hashed directory may be created during an update.' );
	}

	/**
	 * Test that a bulk update (no action in hook_extra) never renames either.
	 *
	 * Core's bulk_upgrade() passes hook_extra without an 'action' key.
	 */
	public function test_should_not_rename_during_bulk_update_without_action() {
		$source = $this->create_source( 'fair-plugin-1.4.1' );
		$remote_source = dirname( untrailingslashit( $source ) );
		$upgrader = $this->get_plugin_upgrader();

		$result = maybe_rename_on_package_download(
			$source,
			$remote_source,
			$upgrader,
			[
				'type' => 'plugin',
				'plugin' => 'fair-plugin/plugin.php',
			]
		);

		$this->assertSame( $source, $result, 'The source path must not change during a bulk update.' );
		$this->assertDirectoryExists( untrailingslashit( $source ), 'The original source directory must be left in place.' );
	}

	/**
	 * Test that a theme update never renames the source directory either.
	 */
	public function test_should_not_rename_during_theme_update() {
		$source = $this->create_source( 'fair-plugin-1.4.1' );
		$remote_source = dirname( untrailingslashit( $source ) );

		$upgrader = new Theme_Upgrader();
		$upgrader->new_theme_data = [ 'Name' => 'Example Package' ];

		$result = maybe_rename_on_package_download(
			$source,
			$remote_source,
			$upgrader,
			[
				'action' => 'update',
				'type' => 'theme',
				'theme' => 'fair-plugin',
			]
		);

		$this->assertSame( $source, $result, 'The source path must not change during a theme update.' );
		$this->assertDirectoryExists( untrailingslashit( $source ), 'The original source directory must be left in place.' );
	}

	/**
	 * Test that an install still renames the source to the DID-hashed slug.
	 */
	public function test_should_rename_during_install_when_directory_does_not_match_slug() {
		$source = $this->create_source( 'fair-plugin-1.4.1' );
		$remote_source = dirname( untrailingslashit( $source ) );
		$upgrader = $this->get_plugin_upgrader();

		$expected = $remote_source . '/fair-plugin-' . get_did_hash( $this->did );

		$result = maybe_rename_on_package_download(
			$source,
			$remote_source,
			$upgrader,
			[
				'action' => 'install',
				'type' => 'plugin',
			]
		);

		$this->assertSame( trailingslashit( $expected ), $result, 'Installs must rename the source to the DID-hashed slug.' );
		$this->assertDirectoryExists( $expected, 'The DID-hashed directory must exist after an install rename.' );
		$this->assertDirectoryDoesNotExist( untrailingslashit( $source ), 'The original source directory must be moved away.' );
	}

	/**
	 * Test that an install does not rename when the directory already matches the slug.
	 */
	public function test_should_not_rename_during_install_when_directory_matches_slug() {
		$source = $this->create_source( 'fair-plugin' );
		$remote_source = dirname( untrailingslashit( $source ) );
		$upgrader = $this->get_plugin_upgrader();

		$result = maybe_rename_on_package_download(
			$source,
			$remote_source,
			$upgrader,
			[
				'action' => 'install',
				'type' => 'plugin',
			]
		);

		$this->assertSame( $source, $result, 'A source directory matching the slug must not be renamed.' );
		$this->assertDirectoryExists( untrailingslashit( $source ), 'The source directory must be left in place.' );
	}

	/**
	 * Test that WP_Error sources are passed through untouched.
	 */
	public function test_should_pass_through_wp_error_source() {
		$error = new WP_Error( 'test.error', 'Test error.' );

		$result = maybe_rename_on_package_download(
			$error,
			'/tmp/does-not-matter',
			$this->get_plugin_upgrader(),
			[
				'action' => 'update',
				'type' => 'plugin',
				'plugin' => 'fair-plugin/plugin.php',
			]
		);

		$this->assertSame( $error, $result, 'WP_Error sources must be passed through.' );
	}

	/**
	 * Seed the transients so that fetch_package_metadata() resolves the
	 * seeded metadata document without any outbound HTTP request.
	 */
	private function seed_package_metadata() {
		$metadata = [
			'id' => $this->did,
			'type' => 'wp-plugin',
			'name' => 'Example Package',
			'slug' => 'fair-plugin',
			'filename' => 'fair-plugin/plugin.php',
			'license' => 'GPLv2 or later',
			'authors' => [
				[
					'name' => 'FAIR Contributors',
					'url' => 'https://fair.pm',
				],
			],
			'security' => [
				[
					'email' => 'security@fair.pm',
				],
			],
			'releases' => [
				[
					'version' => '1.4.1',
					'artifacts' => [
						'package' => [
							[
								'url' => 'https://packages.example.test/fair-plugin-1.4.1.zip',
								'content-type' => 'application/zip',
							],
						],
					],
				],
			],
		];

		// DID document, resolved from the cached DID document transient.
		set_site_transient(
			\FAIR\Packages\CACHE_METADATA_DOCUMENTS . $this->did,
			[
				'id' => $this->did,
				'service' => [
					[
						'id' => $this->did . '#FairPackageManagementRepo',
						'type' => 'FairPackageManagementRepo',
						'serviceEndpoint' => $this->metadata_url,
					],
				],
			]
		);

		// Metadata document response, resolved from the metadata cache transient.
		set_site_transient(
			\FAIR\Packages\CACHE_KEY . md5( $this->metadata_url ),
			[
				'headers' => [
					'content-type' => 'application/json',
				],
				'body' => wp_json_encode( $metadata ),
			]
		);

		// Flag the current download as a FAIR package install.
		set_site_transient( \FAIR\Packages\CACHE_DID_FOR_INSTALL, $this->did );
	}

	/**
	 * Create a fake unzipped package source directory.
	 *
	 * @param string $dir_name Directory name for the package source.
	 * @return string Source path with a trailing slash, as core passes it.
	 */
	private function create_source( string $dir_name ) : string {
		$source = $this->base_dir . '/upgrade/' . $dir_name;
		wp_mkdir_p( $source );
		file_put_contents( $source . '/plugin.php', "<?php\n/** Test fixture. */\n" );

		return trailingslashit( $source );
	}

	/**
	 * Get a Plugin_Upgrader stub with the seeded package name.
	 *
	 * @return Plugin_Upgrader
	 */
	private function get_plugin_upgrader() : Plugin_Upgrader {
		$upgrader = new Plugin_Upgrader();
		$upgrader->new_plugin_data = [ 'Name' => 'Example Package' ];

		return $upgrader;
	}
}
