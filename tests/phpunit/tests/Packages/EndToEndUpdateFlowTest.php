<?php
/**
 * End-to-end update-flow tests for issue #502.
 *
 * Runs a real Plugin_Upgrader::upgrade() against two setups:
 * - installed directory matching the metadata slug -> stays active
 * - installed directory matching neither slug nor slug-didhash -> stays active
 */

use function FAIR\Packages\get_did_hash;

/**
 * Both scenarios run a real Plugin_Upgrader::upgrade() with seeded
 * transients and no outbound HTTP.
 *
 * @covers FAIR\Packages\maybe_rename_on_package_download
 */
class EndToEndUpdateFlowTest extends WP_UnitTestCase {

	/**
	 * DID used for the seeded package.
	 *
	 * @var string
	 */
	private string $did = 'did:plc:example1234567890123456789';

	/**
	 * Metadata endpoint URL used for the seeded package.
	 *
	 * @var string
	 */
	private string $metadata_url = 'https://packages.example.test/fair-metadata.json';

	/**
	 * Base directory for the fake package zips.
	 *
	 * @var string
	 */
	private string $base_dir;

	/**
	 * Plugin files created by the fixture, for cleanup.
	 *
	 * @var string[]
	 */
	private array $created_plugin_files = [];

	/**
	 * Result of the dashboard's reactivation step, if it ran.
	 *
	 * @var bool|WP_Error|null
	 */
	private $reactivation_result = null;

	/**
	 * Register the update-flow hooks that FAIR's Packages\Admin bootstrap
	 * would register in an admin context, because the is_admin() guard
	 * skips them in tests.
	 *
	 * Priority 9 mirrors production ordering: in production,
	 * FAIR\Packages\upgrader_pre_download is registered during plugin
	 * load, so it runs before Updater's verify_signature_on_download
	 * (registered on init at priority 10). A non-false package path
	 * returned by the signature check must not be coerced to a boolean
	 * by upgrader_pre_download()'s `: bool` return type.
	 */
	private function register_admin_update_hooks() {
		add_filter( 'upgrader_package_options', 'FAIR\Packages\cache_did_for_install', 10, 1 );
		add_filter( 'upgrader_pre_download', 'FAIR\Packages\upgrader_pre_download', 9, 1 );
	}

	/**
	 * Seed the package metadata chain and prepare the filesystem.
	 */
	public function set_up() {
		parent::set_up();

		$this->seed_package_metadata();

		$this->base_dir = trailingslashit( get_temp_dir() ) . uniqid( 'fair-e2e-' );
		wp_mkdir_p( $this->base_dir );

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$this->register_admin_update_hooks();

		$this->assertTrue( WP_Filesystem() );
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
		delete_site_transient( \FAIR\Packages\CACHE_RELEASE_PACKAGES );

		foreach ( $this->created_plugin_files as $file ) {
			$dir = dirname( WP_PLUGIN_DIR . '/' . $file );
			if ( is_dir( $dir ) ) {
				$wp_filesystem->delete( $dir, true );
			}
		}

		// Remove any DID-hashed directory a prior run may have left.
		$hashed_dir = WP_PLUGIN_DIR . '/fair-plugin-' . get_did_hash( $this->did );
		if ( $wp_filesystem->is_dir( $hashed_dir ) ) {
			$wp_filesystem->delete( $hashed_dir, true );
		}

		if ( ! empty( $this->base_dir ) ) {
			$wp_filesystem->delete( $this->base_dir, true );
		}

		parent::tear_down();
	}

	/**
	 * Plugin installed under a directory matching the slug; update zip root
	 * directory also matches the slug (official release zip shape).
	 *
	 * Expected: no rename, plugin remains active after update.
	 */
	public function test_update_with_official_zip_shape_keeps_plugin_active() {
		$this->install_local_plugin( 'fair-plugin', '1.3.9' );

		// Root directory matches the metadata slug, as official release zips do.
		$zip = $this->make_package_zip( 'fair-plugin' );
		$this->run_plugin_upgrade( $zip );

		// New version landed in the same, stable directory.
		$this->assertFileExists( WP_PLUGIN_DIR . '/fair-plugin/fair-plugin.php' );
		$this->assertStringContainsString(
			'1.4.1',
			get_file_data( WP_PLUGIN_DIR . '/fair-plugin/fair-plugin.php', [ 'v' => 'Version' ] )['v']
		);
		$this->assertNull( $this->reactivation_result, 'Dashboard reactivation had nothing to do (plugin was never deactivated).' );
		$this->assertTrue( is_plugin_active( 'fair-plugin/fair-plugin.php' ), 'Plugin must stay active.' );
	}

	/**
	 * Regression test for the reported deactivation (issue #502).
	 *
	 * The plugin is installed under a directory that matches neither the
	 * metadata slug nor slug-didhash (e.g. installed from GitHub's
	 * auto-generated "Source code (zip)" asset, whose root is
	 * fair-plugin-1.4.0). Before the fix in this PR, an update renamed the
	 * source to slug-didhash while core derived the destination and the
	 * active entry from hook_extra['plugin'], orphaning the entry and
	 * deactivating the plugin: delete_old_plugin() removed the original
	 * directory and the dashboard's reactivation in
	 * wp_ajax_update_plugin() failed because the file was gone.
	 *
	 * Note: the update path must populate new_plugin_data for this to be
	 * meaningful (core only does so in install() and bulk_upgrade(), never
	 * in single upgrade()); the test hooks check_package() as
	 * bulk_upgrade() does.
	 *
	 * With the fix, updates never rename: the update lands in the
	 * installed directory and the plugin stays active.
	 */
	public function test_update_with_nonstandard_installed_dir_keeps_plugin_active() {
		$this->install_local_plugin( 'fair-plugin-1.4.0', '1.3.9' );

		// Official release zip shape: root directory matches the slug.
		$zip = $this->make_package_zip( 'fair-plugin' );
		$this->run_plugin_upgrade( $zip, 'fair-plugin-1.4.0/fair-plugin.php' );

		// No DID-hashed directory may be created during an update.
		$this->assertFileDoesNotExist( WP_PLUGIN_DIR . '/fair-plugin-' . get_did_hash( $this->did ) . '/fair-plugin.php' );

		// The update landed in the installed directory.
		$this->assertFileExists( WP_PLUGIN_DIR . '/fair-plugin-1.4.0/fair-plugin.php' );
		$this->assertStringContainsString(
			'1.4.1',
			get_file_data( WP_PLUGIN_DIR . '/fair-plugin-1.4.0/fair-plugin.php', [ 'v' => 'Version' ] )['v']
		);

		// The dashboard's reactivation had nothing to do: the plugin was
		// never deactivated.
		$this->assertNull( $this->reactivation_result, 'Plugin must never be deactivated during the update.' );
		$this->assertTrue( is_plugin_active( 'fair-plugin-1.4.0/fair-plugin.php' ), 'Plugin must stay active after update.' );
	}

	/**
	 * Install a local plugin copy with given version and activate it.
	 */
	/**
	 * Install a local plugin copy with the given version and activate it.
	 *
	 * @param string $dir     Directory name for the plugin.
	 * @param string $version Version header value.
	 */
	private function install_local_plugin( string $dir, string $version ) {
		global $wp_filesystem;

		$path = WP_PLUGIN_DIR . '/' . $dir;
		$wp_filesystem->mkdir( $path );
		$php = "<?php\n/*\n * Plugin Name: Example Package\n * Version: {$version}\n */\n";
		$wp_filesystem->put_contents( $path . '/fair-plugin.php', $php );
		$this->created_plugin_files[] = $dir . '/fair-plugin.php';

		activate_plugin( $dir . '/fair-plugin.php' );
		$this->assertTrue( is_plugin_active( $dir . '/fair-plugin.php' ), 'Fixture plugin must start active.' );
	}

	/**
	 * Build a zip with a given root directory name.
	 */
	/**
	 * Build a zip with the given root directory name.
	 *
	 * @param string $root_dir Root directory inside the zip.
	 * @return string Zip path.
	 */
	private function make_package_zip( string $root_dir ) : string {
		$src = $this->base_dir . '/pkg/' . $root_dir;
		wp_mkdir_p( $src );
		file_put_contents( $src . '/fair-plugin.php', "<?php\n/*\n * Plugin Name: Example Package\n * Version: 1.4.1\n */\n" );

		$zip_path = $this->base_dir . '/package.zip';
		$this->assertTrue( class_exists( 'ZipArchive' ), 'ZipArchive required.' );

		$zip = new ZipArchive();
		$this->assertTrue( $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) === true );
		$zip->addFile( $src . '/fair-plugin.php', $root_dir . '/fair-plugin.php' );
		$zip->close();

		return $zip_path;
	}

	/**
	 * Seed update_plugins transient and run a real Plugin_Upgrader::upgrade().
	 */
	/**
	 * Seed the update transient and run a real Plugin_Upgrader::upgrade().
	 *
	 * @param string $zip_path Path to the package zip.
	 * @param string $basename Installed plugin basename for the transient key.
	 */
	private function run_plugin_upgrade( string $zip_path, string $basename = 'fair-plugin/fair-plugin.php' ) {
		// Cache release packages so cache_did_for_install() resolves the DID
		// from the package URL during upgrader_package_options.
		$release = new stdClass();
		$release->version = '1.4.1';
		$release->artifacts = new stdClass();
		$package_artifact = new stdClass();
		$package_artifact->url = $zip_path;
		$package_artifact->{'content-type'} = 'application/zip';
		$release->artifacts->package = [ $package_artifact ];
		set_site_transient( \FAIR\Packages\CACHE_RELEASE_PACKAGES, [ $this->did => $release ] );

		// Core update transient entry, as the real DID update path leaves it.
		$transient = get_site_transient( 'update_plugins' );
		if ( ! is_object( $transient ) ) {
			$transient = new stdClass();
		}
		$transient->response = $transient->response ?? [];
		$transient->response[ $basename ] = (object) [
			'package'     => $zip_path,
			'new_version' => '1.4.1',
			'slug'        => 'fair-plugin',
		];
		set_site_transient( 'update_plugins', $transient );

		$upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );

		// Core populates new_plugin_data (via check_package) only during
		// install() and bulk_upgrade(); single upgrade() never does. Hook it
		// exactly as Plugin_Upgrader::bulk_upgrade() does, which is the
		// update path where FAIR's rename logic can actually fire.
		add_filter( 'upgrader_source_selection', [ $upgrader, 'check_package' ] );
		$result = $upgrader->upgrade( $basename );

		$this->assertNotFalse( $result, 'Upgrade must run.' );
		if ( is_wp_error( $result ) ) {
			$this->fail( 'Upgrade errored: ' . $result->get_error_message() );
		}

		// Core's Plugin_Upgrader deactivates the plugin before upgrading; the
		// dashboard's wp_ajax_update_plugin() reactivates it afterwards using
		// the original basename. Mirror that here, exactly as the admin does.
		$this->reactivation_result = null;
		if ( is_plugin_inactive( $basename ) ) {
			$this->reactivation_result = activate_plugin( $basename, '', false );
		}
	}

	/**
	 * Seed the DID document and metadata document transients (no outbound HTTP).
	 */
	/**
	 * Seed the DID document and metadata document transients.
	 */
	private function seed_package_metadata() {
		$metadata = [
			'id' => $this->did,
			'type' => 'wp-plugin',
			'name' => 'Example Package',
			'slug' => 'fair-plugin',
			'filename' => 'fair-plugin/fair-plugin.php',
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
								'url' => 'placeholder-replaced-per-test',
								'content-type' => 'application/zip',
							],
						],
					],
				],
			],
		];

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

		set_site_transient(
			\FAIR\Packages\CACHE_KEY . md5( $this->metadata_url ),
			[
				'headers' => [ 'content-type' => 'application/json' ],
				'body' => wp_json_encode( $metadata ),
			]
		);
	}
}
