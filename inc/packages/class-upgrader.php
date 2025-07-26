<?php
/**
 * DID Installer.
 *
 * @package FAIR
 */

namespace FAIR\Packages;

use function FAIR\Updater\add_package_to_release_cache;

use WP_Error;
use WP_Upgrader;

/**
 * Class Upgrader.
 */
class Upgrader extends WP_Upgrader {
	/**
	 * Metadata document.
	 *
	 * @var MetadataDocument
	 */
	protected $package;

	/**
	 * Release document.
	 *
	 * @var ReleaseDocument
	 */
	protected $release;

	/**
	 * Is this an upgrade?
	 *
	 * @var bool
	 */
	protected $is_upgrade = false;

	/**
	 * New plugin data.
	 *
	 * @var array
	 */
	protected $new_plugin_data = [];

	/**
	 * New theme data.
	 *
	 * @var array
	 */
	protected $new_theme_data = [];

	/**
	 * Initializes the installation strings.
	 */
	public function install_strings() {
		$this->strings['no_package'] = __( 'Installation package not available.', 'fair' );
		/* translators: %s: Package URL. */
		$this->strings['downloading_package'] = sprintf( __( 'Downloading installation package from %s…', 'fair' ), '<span class="code pre">%s</span>' );
		$this->strings['unpack_package']      = __( 'Unpacking the package…', 'fair' );
		$this->strings['installing_package']  = __( 'Installing the package…', 'fair' );
		$this->strings['remove_old']          = __( 'Removing the current package…', 'fair' );
		$this->strings['remove_old_failed']   = __( 'Could not remove the current package.', 'fair' );
		$this->strings['no_files']            = __( 'The package contains no files.', 'fair' );
		$this->strings['process_failed']      = __( 'Package installation failed.', 'fair' );
		$this->strings['process_success']     = __( 'Package installed successfully.', 'fair' );
		/* translators: 1: package name, 2: package version. */
		$this->strings['process_success_specific'] = __( 'Successfully installed the package <strong>%1$s %2$s</strong>.', 'fair' );
	}

	/**
	 * On run error.
	 *
	 * @param  WP_Error $error Error.
	 * @param  array    $options Options.
	 *
	 * @return WP_Error
	 */
	protected function on_run_error( WP_Error $error, array $options ) {
		$this->skin->error( $error );
		$this->skin->after();
		if ( ! $options['is_multi'] ) {
			$this->skin->footer();
		}

		$this->run_options = [];
		return $error;
	}

	/**
	 * On run failure.
	 *
	 * @param  WP_Error $result Error.
	 * @param  array    $options Options.
	 *
	 * @return void
	 */
	protected function on_run_fail( WP_Error $result, array $options ) {
		// An automatic plugin update will have already performed its rollback.
		if ( ! empty( $this->options['hook_extra']['temp_backup'] ) ) {
			$this->temp_restores[] = $this->options['hook_extra']['temp_backup'];

			/*
			 * Restore the backup on shutdown.
			 * Actions running on `shutdown` are immune to PHP timeouts,
			 * so in case the failure was due to a PHP timeout,
			 * it will still be able to properly restore the previous version.
			 *
			 * Zero arguments are accepted as a string can sometimes be passed
			 * internally during actions, causing an error because
			 * `WP_Upgrader::restore_temp_backup()` expects an array.
			 */
			add_action( 'shutdown', [ $this, 'restore_temp_backup' ], 10, 0 );
		}
		$this->skin->error( $result );

		if ( ! method_exists( $this->skin, 'hide_process_failed' ) || ! $this->skin->hide_process_failed( $result ) ) {
			$this->skin->feedback( 'process_failed' );
		}
	}

	/**
	 * On run complete.
	 *
	 * @param  array $result Result.
	 * @param  array $options Options.
	 *
	 * @return array|WP_Error
	 */
	protected function on_run_complete( $result, $options ) {
		$this->skin->after();

		if ( ! $options['is_multi'] ) {

			/**
			 * Fires when the upgrader process is complete.
			 *
			 * See also {@see 'upgrader_package_options'}.
			 *
			 * @since 3.6.0
			 * @since 3.7.0 Added to WP_Upgrader::run().
			 * @since 4.6.0 `$translations` was added as a possible argument to `$hook_extra`.
			 *
			 * @param WP_Upgrader $upgrader   WP_Upgrader instance. In other contexts this might be a
			 *                                Theme_Upgrader, Plugin_Upgrader, Core_Upgrade, or Language_Pack_Upgrader instance.
			 * @param array       $hook_extra {
			 *     Array of bulk item update data.
			 *
			 *     @type string $action       Type of action. Default 'update'.
			 *     @type string $type         Type of update process. Accepts 'plugin', 'theme', 'translation', or 'core'.
			 *     @type bool   $bulk         Whether the update process is a bulk update. Default true.
			 *     @type array  $plugins      Array of the basename paths of the plugins' main files.
			 *     @type array  $themes       The theme slugs.
			 *     @type array  $translations {
			 *         Array of translations update data.
			 *
			 *         @type string $language The locale the translation is for.
			 *         @type string $type     Type of translation. Accepts 'plugin', 'theme', or 'core'.
			 *         @type string $slug     Text domain the translation is for. The slug of a theme/plugin or
			 *                                'default' for core translations.
			 *         @type string $version  The version of a theme, plugin, or core.
			 *     }
			 * }
			 */
			do_action( 'upgrader_process_complete', $this, $options['hook_extra'] );

			$this->skin->footer();
		}

		return $result;
	}

	/**
	 * Deprecated. Install/upgrade the package.
	 *
	 * @internal Provided only for compatibility with the parent class's type. Do not use.
	 * @param array $options Options.
	 * @return WP_Error
	 */
	public function run( $options ) {
		_doing_it_wrong( get_class( $this ) . '::' . __METHOD__, 'Use run_install instead.', '' );
		return new WP_Error( 'fair.packages.upgrader.run', 'Not implemented' );
	}

	/**
	 * Installs/upgrades the package release.
	 *
	 * Attempts to download the package (if it is not a local file), unpack it, and
	 * install it in the destination folder.
	 *
	 * @since 2.8.0
	 *
	 * @param string $destination Full path to the destination folder.
	 * @param array $options {
	 *     Array or string of arguments for upgrading/installing a package.
	 *
	 *     @type bool   $clear_destination           Whether to delete any files already in the
	 *                                               destination folder. Default false.
	 *     @type bool   $clear_working               Whether to delete the files from the working
	 *                                               directory after copying them to the destination.
	 *                                               Default true.
	 *     @type bool   $abort_if_destination_exists Whether to abort the installation if the destination
	 *                                               folder already exists. When true, `$clear_destination`
	 *                                               should be false. Default true.
	 *     @type bool   $is_multi                    Whether this run is one of multiple upgrade/installation
	 *                                               actions being performed in bulk. When true, the skin
	 *                                               WP_Upgrader::header() and WP_Upgrader::footer()
	 *                                               aren't called. Default false.
	 *     @type array  $hook_extra                  Extra arguments to pass to the filter hooks called by
	 *                                               WP_Upgrader::run().
	 * }
	 * @return array|false|WP_Error The result from self::install_package() on success, otherwise a WP_Error,
	 *                              or false if unable to connect to the filesystem.
	 */
	protected function run_install( string $destination, $options ) {
		$defaults = [
			'clear_destination' => false,
			'clear_working' => true,
			'abort_if_destination_exists' => true, // Abort if the destination directory exists. Pass clear_destination as false please.
			'is_multi' => false,
			'hook_extra' => [], // Pass any extra $hook_extra args here, this will be passed to any hooked filters.
		];

		$options = wp_parse_args( $options, $defaults );
		$options['destination'] = $destination;

		// Connect to the filesystem first.
		$res = $this->fs_connect( [ WP_CONTENT_DIR, $options['destination'] ] );
		// Mainly for non-connected filesystem.
		if ( ! $res ) {
			if ( ! $options['is_multi'] ) {
				$this->skin->footer();
			}
			return false;
		}

		$this->skin->before();
		if ( is_wp_error( $res ) ) {
			return $this->on_run_error( $res, $options );
		}

		// Resolve the release artifact to a URL.
		$artifact = pick_artifact_by_lang( $this->release->artifacts->package );

		add_package_to_release_cache( $this->package->id );

		// Download the package.
		$path = $this->download_package( $artifact->url, false, $options['hook_extra'] );
		if ( is_wp_error( $path ) ) {
			return $this->on_run_error( $path, $options );
		}

		// Verify the signature.
		/**
		 * Should we verify the signature?
		 *
		 * @todo This should be removed entirely once the decoding is sorted.
		 */
		$should_verify = apply_filters( 'fair.packages.upgrader.verify_signatures', false );
		if ( $should_verify ) {
			add_filter( 'wp_trusted_keys', [ $this, 'set_trusted_keys' ], 100 );
			$verification = verify_file_signature( $path, $artifact->signature );
			remove_filter( 'wp_trusted_keys', [ $this, 'set_trusted_keys' ], 100 );
			if ( is_wp_error( $verification ) ) {
				return $this->on_run_error( $verification, $options );
			}
		}

		// Unzips the file into a temporary directory.
		$working_dir = $this->unpack_package( $path, true );
		if ( is_wp_error( $working_dir ) ) {
			return $this->on_run_error( $working_dir, $options );
		}

		// With the given options, this installs it to the destination directory.
		add_filter( 'upgrader_source_selection', [ $this, 'check_requirements' ] );
		add_filter( 'upgrader_source_selection', [ $this, 'rename_source_selection' ], 10, 2 );
		$result = $this->install_package( array_merge( $options, [
			'source' => $working_dir,
		] ) );
		remove_filter( 'upgrader_source_selection', [ $this, 'check_requirements' ] );
		remove_filter( 'upgrade_source_selection', [ $this, 'rename_source_selection' ], 10 );

		/**
		 * Filters the result of WP_Upgrader::install_package().
		 *
		 * @since 5.7.0
		 *
		 * @param array|WP_Error $result     Result from WP_Upgrader::install_package().
		 * @param array          $hook_extra Extra arguments passed to hooked filters.
		 */
		$result = apply_filters( 'upgrader_install_package_result', $result, $options['hook_extra'] );

		$this->skin->set_result( $result );

		// Clean up the backup kept in the temporary backup directory.
		if ( ! empty( $options['hook_extra']['temp_backup'] ) ) {
			// Delete the backup on `shutdown` to avoid a PHP timeout.
			add_action( 'shutdown', [ $this, 'delete_temp_backup' ], 100, 0 );
		}

		if ( is_wp_error( $result ) ) {
			$this->on_run_fail( $result, $options );
		} else {
			// Installation succeeded.
			$this->skin->feedback( 'process_success' );
		}

		return $this->on_run_complete( $result, $options );
	}

	/**
	 * Set trusted keys.
	 *
	 * @return array
	 */
	protected function set_trusted_keys() {
		$doc = get_did_document( $this->package->id );
		if ( is_wp_error( $doc ) ) {
			return [];
		}

		$valid_keys = $doc->get_fair_signing_keys();

		// todo: re-encode from multibase to base64.
		// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
		// return $valid_keys;
		return [];
	}

	/**
	 * Install plugin.
	 *
	 * @param  bool $clear_cache Boolean to clear cache.
	 * @param  bool $overwrite Boolean to overwrite.
	 *
	 * @return bool|WP_Error True if the installation was successful, false or a WP_Error object otherwise.
	 */
	protected function install_plugin( $clear_cache, $overwrite ) {
		if ( $clear_cache ) {
			// Clear cache so wp_update_plugins() knows about the new plugin.
			add_action( 'upgrader_process_complete', 'wp_clean_plugins_cache', 9, 0 );
		}

		$this->run_install(
			WP_PLUGIN_DIR,
			[
				'clear_destination' => $overwrite,
				'clear_working'     => true,
				'hook_extra'        => [
					'type'   => 'plugin',
					'action' => 'install',
				],
			]
		);

		if ( $clear_cache ) {
			remove_action( 'upgrader_process_complete', 'wp_clean_plugins_cache', 9 );
		}

		if ( ! $this->result || is_wp_error( $this->result ) ) {
			return $this->result;
		}

		// Force refresh of plugin update information.
		wp_clean_plugins_cache( $clear_cache );

		if ( $overwrite ) {
			/**
			 * Fires when the upgrader has successfully overwritten a currently installed
			 * plugin or theme with an uploaded zip package.
			 *
			 * @since 5.5.0
			 *
			 * @param string  $package      The package file.
			 * @param array   $data         The new plugin or theme data.
			 * @param string  $package_type The package type ('plugin' or 'theme').
			 */
			do_action( 'upgrader_overwrote_package', $this->package, $this->new_plugin_data, 'plugin' );
		}

		return true;
	}

	/**
	 * Install a theme package.
	 *
	 * @param  bool $clear_cache Boolean to clear cache.
	 * @param  bool $overwrite Boolean to overwrite.

	 * @return bool|WP_Error True if the installation was successful, false or a WP_Error object otherwise.
	 */
	public function install_theme( $clear_cache, $overwrite ) {
		if ( $clear_cache ) {
			// Clear cache so wp_update_themes() knows about the new theme.
			add_action( 'upgrader_process_complete', 'wp_clean_themes_cache', 9, 0 );
		}

		$this->run_install( get_theme_root(), [
			'clear_destination' => $overwrite,
			'clear_working' => true,
			'hook_extra' => [
				'type' => 'theme',
				'action' => 'install',
			],
		] );

		if ( $clear_cache ) {
			remove_action( 'upgrader_process_complete', 'wp_clean_themes_cache', 9 );
		}

		if ( ! $this->result || is_wp_error( $this->result ) ) {
			return $this->result;
		}

		// Refresh the Theme Update information.
		wp_clean_themes_cache( $clear_cache );

		if ( $overwrite ) {
			/** This action is documented in wp-admin/includes/class-plugin-upgrader.php */
			do_action( 'upgrader_overwrote_package', $this->package, $this->new_theme_data, 'theme' );
		}

		return true;
	}

	/**
	 * Install a package.
	 *
	 * @since 2.8.0
	 * @since 3.7.0 The `$args` parameter was added, making clearing the plugin update cache optional.
	 *
	 * @param MetadataDocument $package The full local path or URI of the package.
	 * @param ReleaseDocument $release Release document.
	 * @param  bool $clear_cache Whether to clear the plugin updates cache if successful.
	 *                                    Default true.
	 * @param  bool $overwrite Whether to overwrite plugin folder.
	 *                                    Default true.
	 * @return bool|WP_Error True if the installation was successful, false or a WP_Error otherwise.
	 */
	public function install( MetadataDocument $package, ReleaseDocument $release, $clear_cache = true, $overwrite = false ) {
		$this->init();
		$this->install_strings();

		$this->package = $package;
		$this->release = $release;

		switch ( $this->package->type ) {
			case 'wp-plugin':
				return $this->install_plugin( $clear_cache, $overwrite );

			case 'wp-theme':
				return $this->install_theme( $clear_cache, $overwrite );

			default:
				return new WP_Error( 'fair.packages.upgrader.install.invalid_type', 'Invalid package type.' );
		}
	}

	/**
	 * Retrieves the hashed path to the file that contains the plugin info.
	 *
	 * This isn't used internally in the class, but is called by the skins.
	 *
	 * @since WordPress 2.8.0
	 *
	 * @return string|false The full path to the main plugin file, or false.
	 */
	public function plugin_info() {
		if ( ! isset( $this->package ) ) {
			return false;
		}

		return get_hashed_filename( $this->package );
	}

	/**
	 * Gets the WP_Theme object for a theme.
	 *
	 * @since WordPress 2.8.0
	 * @since WordPress 3.0.0 The `$theme` argument was added.
	 *
	 * @param string $theme The directory name of the theme. This is optional, and if not supplied,
	 *                      the directory name from the last result will be used.
	 * @return WP_Theme|false The theme's info object, or false `$theme` is not supplied
	 *                        and the last result isn't set.
	 */
	public function theme_info( $theme = null ) {
		if ( empty( $theme ) ) {
			if ( ! empty( $this->result['destination_name'] ) ) {
				$theme = $this->result['destination_name'];
			} else {
				return false;
			}
		}

		$theme = wp_get_theme( $theme );
		$theme->cache_delete();

		return $theme;
	}

	/**
	 * Checks that the source package contains a valid plugin.
	 *
	 * Hooked to the {@see 'upgrader_source_selection'} filter by Plugin_Upgrader::install().
	 *
	 * @since 3.3.0
	 *
	 * @global WP_Filesystem_Base $wp_filesystem WordPress filesystem subclass.
	 *
	 * @param string $source The path to the downloaded package source.
	 * @return string|WP_Error The source as passed, or a WP_Error object on failure.
	 */
	public function check_requirements( $source ) {
		global $wp_filesystem;

		$wp_version = wp_get_wp_version();
		$this->new_plugin_data = [];

		if ( is_wp_error( $source ) ) {
			return $source;
		}

		$working_directory = str_replace( $wp_filesystem->wp_content_dir(), trailingslashit( WP_CONTENT_DIR ), $source );
		if ( ! is_dir( $working_directory ) ) { // Confidence check, if the above fails, let's not prevent installation.
			return $source;
		}

		switch ( $this->package->type ) {
			case 'wp-plugin':
				$err = $this->validate_plugin( $working_directory );
				break;

			case 'wp-theme':
				$err = $this->validate_theme( $working_directory );
				break;

			default:
				return new WP_Error( 'unsupported-type' );
		}
		if ( is_wp_error( $err ) ) {
			return $err;
		}

		// Get environmental requirements if set.
		foreach ( $this->release->requires as $pkg => $ver ) {
			switch ( true ) {
				// WordPress requirements.
				case ( $pkg === 'env:wp' ):
					if ( substr( $ver, 0, 2 ) !== '>=' ) {
						return new WP_Error( 'unsupported_version_constraint' );
					}
					if ( ! is_wp_version_compatible( $ver ) ) {
						$error = sprintf(
							/* translators: 1: Current WordPress version, 2: Version required by the package. */
							__( 'Your WordPress version is %1$s, however the package requires %2$s.', 'fair' ),
							$wp_version,
							$ver
						);

						return new WP_Error( 'incompatible_wp_required_version', $this->strings['incompatible_archive'], $error );
					}
					break;

				// PHP requirements.
				case ( $pkg === 'env:php' ):
					if ( substr( $ver, 0, 2 ) !== '>=' ) {
						return new WP_Error( 'unsupported_version_constraint' );
					}
					if ( ! is_php_version_compatible( $ver ) ) {
						$error = sprintf(
							/* translators: 1: Current PHP version, 2: Version required by the package. */
							__( 'The PHP version on your server is %1$s, however the package requires %2$s.', 'fair' ),
							PHP_VERSION,
							$ver
						);

						return new WP_Error( 'incompatible_php_required_version', $this->strings['incompatible_archive'], $error );
					}
					break;

				// PHP extension requirements.
				case str_starts_with( $pkg, 'env:php-' ):
					$php_ext = substr( $pkg, 8 );
					if ( extension_loaded( $php_ext ) ) {
						// Extension is loaded, skip.
						continue 2;
					}

					if ( $ver !== '*' ) {
						return new WP_Error( 'unsupported_version_constraint' );
					}

					return new WP_Error( 'missing_php_extension' );

				default:
					// Skip.
					return new WP_Error( 'unsupported_version_constraint' );
			}
		}

		// No errors, proceed.
		return $source;
	}

	/**
	 * Validate plugin.
	 *
	 * @param  string $dir Directory containing plugin files.
	 *
	 * @return void|WP_Error
	 */
	protected function validate_plugin( $dir ) {
		// Check that the folder contains at least 1 valid plugin.
		$files = glob( $dir . '*.php' );
		$data = null;
		if ( $files ) {
			foreach ( $files as $file ) {
				$info = get_plugin_data( $file, false, false );
				if ( ! empty( $info['Name'] ) ) {
					$data = $info;
					break;
				}
			}
		}

		if ( empty( $data ) ) {
			return new WP_Error( 'incompatible_archive_no_plugins', $this->strings['incompatible_archive'], __( 'No valid plugins were found.', 'fair' ) );
		}
	}

	/**
	 * Validate theme.
	 *
	 * @param  string $dir Directory containing theme files.
	 *
	 * @return void|WP_Error
	 */
	protected function validate_theme( $dir ) {
		// A proper archive should have a style.css file in the single subdirectory.
		if ( ! file_exists( $dir . 'style.css' ) ) {
			return new WP_Error(
				'incompatible_archive_theme_no_style',
				$this->strings['incompatible_archive'],
				sprintf(
					/* translators: %s: style.css */
					__( 'The theme is missing the %s stylesheet.', 'fair' ),
					'<code>style.css</code>'
				)
			);
		}

		// All these headers are needed on Theme_Installer_Skin::do_overwrite().
		$info = get_file_data(
			$dir . 'style.css',
			[
				'Name'        => 'Theme Name',
				'Version'     => 'Version',
				'Author'      => 'Author',
				'Template'    => 'Template',
				'RequiresWP'  => 'Requires at least',
				'RequiresPHP' => 'Requires PHP',
			]
		);

		if ( empty( $info['Name'] ) ) {
			return new WP_Error(
				'incompatible_archive_theme_no_name',
				$this->strings['incompatible_archive'],
				sprintf(
					/* translators: %s: style.css */
					__( 'The %s stylesheet does not contain a valid theme header.', 'fair' ),
					'<code>style.css</code>'
				)
			);
		}

		/*
		 * Parent themes must contain an index file:
		 * - classic themes require /index.php
		 * - block themes require /templates/index.html or block-templates/index.html (deprecated 5.9.0).
		 */
		if (
			empty( $info['Template'] ) &&
			! file_exists( $dir . 'index.php' ) &&
			! file_exists( $dir . 'templates/index.html' ) &&
			! file_exists( $dir . 'block-templates/index.html' )
		) {
			return new WP_Error(
				'incompatible_archive_theme_no_index',
				$this->strings['incompatible_archive'],
				sprintf(
					/* translators: 1: templates/index.html, 2: index.php, 3: Documentation URL, 4: Template, 5: style.css */
					__( 'Template is missing. Standalone themes need to have a %1$s or %2$s template file. <a href="%3$s">Child themes</a> need to have a %4$s header in the %5$s stylesheet.', 'fair' ),
					'<code>templates/index.html</code>',
					'<code>index.php</code>',
					__( 'https://developer.wordpress.org/themes/advanced-topics/child-themes/', 'fair' ),
					'<code>Template</code>',
					'<code>style.css</code>'
				)
			);
		}

		$this->new_theme_data = $info;
	}

}
