<?php
/**
 * Update FAIR packages.
 *
 * @package FAIR
 */

namespace FAIR\Updater;

use FAIR\Packages;
use Plugin_Upgrader;
use stdClass;
use Theme_Upgrader;
use TypeError;
use WP_Error;
use WP_Upgrader;

/**
 * Class FAIR_Updater.
 */
class Updater {

	/**
	 * Registered plugins.
	 *
	 * @var array<string, PluginPackage>
	 */
	private static array $plugins = [];

	/**
	 * Registered themes.
	 *
	 * @var array<string, ThemePackage>
	 */
	private static array $themes = [];

	/**
	 * Check if we should run on the current page.
	 *
	 * @global string $pagenow Current page.
	 */
	public static function should_run_on_current_page(): bool {
		global $pagenow;

		// Needed for mu-plugin.
		if ( ! isset( $pagenow ) ) {
			// phpcs:ignore HM.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.WP.DeprecatedFunctions.sanitize_urlFound
			$php_self = isset( $_SERVER['PHP_SELF'] ) ? sanitize_url( wp_unslash( $_SERVER['PHP_SELF'] ) ) : null;
			if ( null !== $php_self ) {
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				$pagenow = basename( $php_self );
			}
		}

		// Only run on the following pages.
		$pages            = [ 'update-core.php', 'update.php', 'plugins.php', 'themes.php' ];
		$view_details     = [ 'plugin-install.php', 'theme-install.php' ];
		$autoupdate_pages = [ 'admin-ajax.php', 'index.php', 'wp-cron.php' ];
		if ( ! in_array( $pagenow, array_merge( $pages, $view_details, $autoupdate_pages ), true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Load hooks.
	 *
	 * @return void
	 */
	public static function load_hooks() {
		add_filter( 'upgrader_source_selection', [ __CLASS__, 'upgrader_source_selection' ], 10, 4 );
		add_filter( 'plugins_api', [ __CLASS__, 'plugin_api_details' ], 99, 3 );
		add_filter( 'themes_api', [ __CLASS__, 'theme_api_details' ], 99, 3 );

		add_filter( 'site_transient_update_plugins', [ __CLASS__, 'handle_update_plugins_transient' ], 20, 1 );
		add_filter( 'site_transient_update_themes', [ __CLASS__, 'handle_update_themes_transient' ], 20, 1 );

		if ( ! is_multisite() ) {
			add_filter( 'wp_prepare_themes_for_js', [ __CLASS__, 'customize_theme_update_html' ] );
		}

		/**
		 * Filter whether to verify FAIR package signatures during update.
		 *
		 * @param bool $verify Whether to verify signatures. Default true.
		 * @return bool
		 */
		if ( apply_filters( 'fair.packages.updater.verify_signatures', true ) ) {
			add_filter( 'upgrader_pre_download', 'FAIR\\Updater\\verify_signature_on_download', 10, 4 );
		}

		foreach ( self::$plugins as $package ) {
			Packages\add_package_to_release_cache( $package->did );
		}
		foreach ( self::$themes as $package ) {
			Packages\add_package_to_release_cache( $package->did );
		}
	}

	/**
	 * Correctly rename dependency for activation.
	 *
	 * @param string|WP_Error $source    Path of $source, or a WP_Error object.
	 * @param string      $remote_source Path of $remote_source.
	 * @param WP_Upgrader $upgrader      An Upgrader object.
	 * @param array       $hook_extra    Array of hook data.
	 *
	 * @throws TypeError If the type of $upgrader is not correct.
	 *
	 * @return string|WP_Error
	 */
	public static function upgrader_source_selection( $source, string $remote_source, WP_Upgrader $upgrader, $hook_extra = null ) {
		global $wp_filesystem;

		// Exit early for errors.
		if ( is_wp_error( $source ) ) {
			return $source;
		}

		$new_source = $source;

		// Exit if installing.
		if ( isset( $hook_extra['action'] ) && 'install' === $hook_extra['action'] ) {
			return $source;
		}

		if ( ! $upgrader instanceof Plugin_Upgrader && ! $upgrader instanceof Theme_Upgrader ) {
			throw new TypeError( __METHOD__ . '(): Argument #3 ($upgrader) must be of type Plugin_Upgrader|Theme_Upgrader, ' . esc_attr( gettype( $upgrader ) ) . ' given.' );
		}

		// Rename plugins.
		if ( $upgrader instanceof Plugin_Upgrader ) {
			if ( ! isset( $hook_extra['plugin'] ) ) {
				return $source;
			}
			$slug       = dirname( $hook_extra['plugin'] );
			$new_source = trailingslashit( $remote_source ) . $slug;
		}

		// Rename themes.
		if ( $upgrader instanceof Theme_Upgrader ) {
			if ( ! isset( $hook_extra['theme'] ) ) {
				return $source;
			}
			$slug       = $hook_extra['theme'];
			$new_source = trailingslashit( $remote_source ) . $slug;
		}

		if ( basename( $source ) === $slug ) {
			return $source;
		}

		if ( trailingslashit( strtolower( $source ) ) !== trailingslashit( strtolower( $new_source ) ) ) {
			$wp_filesystem->move( $source, $new_source, true );
		}

		return trailingslashit( $new_source );
	}

	/**
	 * Put changelog in plugins_api, return WP.org data as appropriate
	 *
	 * @param bool     $result   Default false.
	 * @param string   $action   The type of information being requested from the Plugin Installation API.
	 * @param stdClass $response Repo API arguments.
	 *
	 * @return stdClass|bool
	 */
	public static function plugin_api_details( $result, string $action, stdClass $response ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		return self::handle_plugin_api( $result, $response->slug ?? '' );
	}

	/**
	 * Put changelog in themes_api, return WP.org data as appropriate
	 *
	 * @param bool     $result   Default false.
	 * @param string   $action   The type of information being requested from the Theme Installation API.
	 * @param stdClass $response Repo API arguments.
	 *
	 * @return stdClass|bool
	 */
	public static function theme_api_details( $result, string $action, stdClass $response ) {
		if ( 'theme_information' !== $action ) {
			return $result;
		}

		return self::handle_theme_api( $result, $response->slug ?? '' );
	}

	/**
	 * Find a package by its API slug.
	 *
	 * @param bool|object $result   The result object or false.
	 * @param string      $slug     The package slug.
	 * @param Package[]   $packages The packages to search.
	 * @return bool|object The result.
	 */
	private static function find_package_by_api_slug( $result, string $slug, array $packages ) {
		if ( empty( $slug ) ) {
			return $result;
		}

		foreach ( $packages as $package ) {
			$metadata = $package->get_metadata();
			if ( is_wp_error( $metadata ) || ! $metadata ) {
				continue;
			}

			// Check if slug matches (with or without DID hash suffix).
			$slug_arr = [ $metadata->slug, $metadata->slug . '-' . Packages\get_did_hash( $package->did ) ];
			if ( in_array( $slug, $slug_arr, true ) ) {
				return (object) Packages\get_package_data( $package->did );
			}
		}

		return $result;
	}

	/**
	 * Handle site_transient_update_plugins filter.
	 *
	 * @param stdClass $transient Plugin|Theme update transient.
	 * @return stdClass The modified transient.
	 */
	public static function handle_update_plugins_transient( $transient ) {
		$transient = self::update_site_transient( $transient, self::$plugins );

		// WordPress expects plugin responses as objects.
		foreach ( $transient->response ?? [] as $key => $value ) {
			$transient->response[ $key ] = (object) $value;
		}
		foreach ( $transient->no_update ?? [] as $key => $value ) {
			$transient->no_update[ $key ] = (object) $value;
		}

		return $transient;
	}

	/**
	 * Handle site_transient_update_themes filter.
	 *
	 * @param stdClass $transient Plugin|Theme update transient.
	 * @return stdClass The modified transient.
	 */
	public static function handle_update_themes_transient( $transient ) {
		return self::update_site_transient( $transient, self::$themes );
	}

	/**
	 * Hook into site_transient_update_{plugins|themes} to update from GitHub.
	 *
	 * @param stdClass $transient Plugin|Theme update transient.
	 * @param array<string, Package> $packages Array of packages to process.
	 * @return stdClass
	 */
	private static function update_site_transient( $transient, array $packages ) {
		// needed to fix PHP 7.4 warning.
		if ( ! is_object( $transient ) ) {
			$transient = new stdClass();
		}

		foreach ( $packages as $package ) {
			if ( empty( $package->filepath ) || empty( $package->local_version ) ) {
				continue;
			}

			$release = $package->get_release();
			if ( is_wp_error( $release ) || ! $release ) {
				continue;
			}

			$response = Packages\get_package_data( $package->did );
			if ( is_wp_error( $response ) ) {
				continue;
			}

			$rel_path = $package->get_relative_path();

			$response['slug'] = $response['slug_didhash'];

			$is_compatible = Packages\check_requirements( $release );

			if ( $is_compatible && version_compare( $release->version, $package->local_version, '>' ) ) {
				$transient->response[ $rel_path ] = $response;
			} else {
				// Add repo without update to $transient->no_update for 'View details' link.
				$transient->no_update[ $rel_path ] = $response;
			}
		}

		return $transient;
	}

	/**
	 * Call theme messaging for single site installation.
	 *
	 * @author Seth Carstens
	 *
	 * @param array $prepared_themes Array of prepared themes.
	 *
	 * @return array
	 */
	public static function customize_theme_update_html( $prepared_themes ) {
		foreach ( self::$themes as $package ) {
			$theme = $package->get_metadata();
			if ( is_wp_error( $theme ) || ! $theme ) {
				continue;
			}

			if ( ! isset( $prepared_themes[ $theme->slug ] ) ) {
				continue;
			}

			if ( ! empty( $prepared_themes[ $theme->slug ]['hasUpdate'] ) ) {
				$prepared_themes[ $theme->slug ]['update'] = self::append_theme_actions_content( $theme );
			} else {
				$prepared_themes[ $theme->slug ]['description'] .= self::append_theme_actions_content( $theme );
			}
		}

		return $prepared_themes;
	}

	/**
	 * Create theme update messaging for single site installation.
	 *
	 * @author Seth Carstens
	 *
	 * @access protected
	 * @codeCoverageIgnore
	 *
	 * @param stdClass $theme Theme object.
	 *
	 * @return string (content buffer)
	 */
	private static function append_theme_actions_content( $theme ) {
		$details_url       = esc_attr(
			add_query_arg(
				[
					'tab'       => 'theme-information',
					'theme'     => $theme->slug,
					'TB_iframe' => 'true',
					'width'     => 270,
					'height'    => 400,
				],
				self_admin_url( 'theme-install.php' )
			)
		);
		$nonced_update_url = wp_nonce_url(
			esc_attr(
				add_query_arg(
					[
						'action' => 'upgrade-theme',
						'theme'  => rawurlencode( $theme->slug ),
					],
					self_admin_url( 'update.php' )
				)
			),
			'upgrade-theme_' . $theme->slug
		);

		$current = get_site_transient( 'update_themes' );

		/**
		 * Display theme update links.
		 */
		ob_start();
		if ( isset( $current->response[ $theme->slug ] ) ) {
			?>
		<p>
			<strong>
				<?php
				printf(
					/* translators: %s: theme name */
					esc_html__( 'There is a new version of %s available.', 'fair' ),
					esc_attr( $theme->name )
				);
					printf(
						' <a href="%s" class="thickbox open-plugin-details-modal" title="%s">',
						esc_url( $details_url ),
						esc_attr( $theme->name )
					);
				if ( ! empty( $current->response[ $theme->slug ]['package'] ) ) {
					printf(
					/* translators: 1: opening anchor with version number, 2: closing anchor tag, 3: opening anchor with update URL */
						esc_html__( 'View version %1$s details%2$s or %3$supdate now%2$s.', 'fair' ),
						$theme->remote_version = isset( $theme->remote_version ) ? esc_attr( $theme->remote_version ) : null,
						'</a>',
						sprintf(
							/* translators: %s: theme name */
							'<a aria-label="' . esc_attr__( '%s: update now', 'fair' ) . '" id="update-theme" data-slug="' . esc_attr( $theme->slug ) . '" href="' . esc_url( $nonced_update_url ) . '">',
							esc_attr( $theme->name )
						)
					);
				} else {
					printf(
					/* translators: 1: opening anchor with version number, 2: closing anchor tag, 3: opening anchor with update URL */
						esc_html__( 'View version %1$s details%2$s.', 'fair' ),
						$theme->remote_version = isset( $theme->remote_version ) ? esc_attr( $theme->remote_version ) : null,
						'</a>'
					);
					echo(
						'<p><i>' . esc_html__( 'Automatic update is unavailable for this theme.', 'fair' ) . '</i></p>'
					);
				}
				?>
			</strong>
		</p>
			<?php
		}

		return trim( ob_get_clean(), '1' );
	}

	/**
	 * Handle plugin API requests.
	 *
	 * @param bool|object $result The result object or false.
	 * @param string      $slug   The plugin slug.
	 * @return bool|object The result.
	 */
	private static function handle_plugin_api( $result, string $slug ) {
		return self::find_package_by_api_slug( $result, $slug, self::$plugins );
	}

	/**
	 * Handle theme API requests.
	 *
	 * @param bool|object $result The result object or false.
	 * @param string      $slug   The theme slug.
	 * @return bool|object The result.
	 */
	private static function handle_theme_api( $result, string $slug ) {
		return self::find_package_by_api_slug( $result, $slug, self::$themes );
	}

	/**
	 * Register a plugin with the registry.
	 *
	 * @param string $did      The DID of the plugin.
	 * @param string $filepath Absolute path to the main plugin file.
	 */
	public static function register_plugin( string $did, string $filepath ): void {
		self::$plugins[ $did ] = new PluginPackage( $did, $filepath );
	}

	/**
	 * Register a theme with the registry.
	 *
	 * @param string $did      The DID of the theme.
	 * @param string $filepath Absolute path to the theme's style.css file.
	 */
	public static function register_theme( string $did, string $filepath ): void {
		self::$themes[ $did ] = new ThemePackage( $did, $filepath );
	}

	/**
	 * Get a plugin by DID.
	 *
	 * @param string $did The DID to look up.
	 */
	public static function get_plugin( string $did ): ?PluginPackage {
		return self::$plugins[ $did ] ?? null;
	}

	/**
	 * Get a theme by DID.
	 *
	 * @param string $did The DID to look up.
	 */
	public static function get_theme( string $did ): ?ThemePackage {
		return self::$themes[ $did ] ?? null;
	}

	/**
	 * Get all registered plugins.
	 *
	 * @return array<string, PluginPackage> All registered plugins.
	 */
	public static function get_plugins(): array {
		return self::$plugins;
	}

	/**
	 * Get all registered themes.
	 *
	 * @return array<string, ThemePackage> All registered themes.
	 */
	public static function get_themes(): array {
		return self::$themes;
	}

	/**
	 * Find a plugin by the plugin file path (relative to plugins directory).
	 *
	 * @param string $plugin_file Plugin file path relative to plugins directory (e.g., 'my-plugin/my-plugin.php').
	 */
	public static function get_plugin_by_file( string $plugin_file ): ?PluginPackage {
		$plugin_path = trailingslashit( WP_PLUGIN_DIR ) . $plugin_file;

		foreach ( self::$plugins as $package ) {
			if ( $package->filepath === $plugin_path ) {
				return $package;
			}
		}

		return null;
	}

	/**
	 * Find a plugin by its slug.
	 *
	 * @param string $slug The plugin directory name.
	 */
	public static function get_plugin_by_slug( string $slug ): ?PluginPackage {
		foreach ( self::$plugins as $package ) {
			if ( $package->get_slug() === $slug ) {
				return $package;
			}
		}

		return null;
	}

	/**
	 * Find a theme by its slug.
	 *
	 * @param string $slug The theme stylesheet.
	 */
	public static function get_theme_by_slug( string $slug ): ?ThemePackage {
		foreach ( self::$themes as $package ) {
			if ( $package->get_slug() === $slug ) {
				return $package;
			}
		}

		return null;
	}

	/**
	 * Reset the registry.
	 */
	public static function reset(): void {
		self::$plugins = [];
		self::$themes  = [];
	}
}
