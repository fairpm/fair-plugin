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
	 * DID.
	 *
	 * @var string
	 */
	protected $did;

	/**
	 * Absolute path to the "main" file.
	 *
	 * For plugins, this is the PHP file with the plugin header. For themes,
	 * this is the style.css file.
	 *
	 * @var string|null
	 */
	protected $filepath;

	/**
	 * Current installed version of the package.
	 *
	 * @var string|null
	 */
	protected $local_version;

	/**
	 * Package type, plugin or theme.
	 *
	 * @var string
	 */
	protected $type;

	/**
	 * Metadata document.
	 *
	 * @var \FAIR\Packages\MetadataDocument
	 */
	protected $metadata;

	/**
	 * Release document.
	 *
	 * @var \FAIR\Packages\ReleaseDocument
	 */
	protected $release;

	/**
	 * Constructor.
	 *
	 * @param string $did DID.
	 * @param string $filepath Absolute file path.
	 */
	public function __construct( string $did, string $filepath = '' ) {
		$this->did = $did;
		$this->filepath = $filepath;
		$this->local_version = $filepath ? get_file_data( $filepath, [ 'Version' => 'Version' ] )['Version'] : null;
	}

	/**
	 * Get API data.
	 *
	 * @global string $pagenow Current page.
	 * @return void|WP_Error
	 */
	public function run() {
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
			return;
		}

		$this->metadata = Packages\fetch_package_metadata( $this->did );
		if ( is_wp_error( $this->metadata ) ) {
			return $this->metadata;
		}
		$this->release = Packages\get_latest_release_from_did( $this->did );
		if ( is_wp_error( $this->release ) ) {
			return $this->release;
		}
		$this->type = str_replace( 'wp-', '', $this->metadata->type );

		$this->load_hooks();
	}

	/**
	 * Load hooks.
	 *
	 * @return void
	 */
	public function load_hooks() {
		add_filter( 'upgrader_source_selection', [ $this, 'upgrader_source_selection' ], 10, 4 );
		add_filter( "{$this->type}s_api", [ $this, 'repo_api_details' ], 99, 3 );

		if ( ! empty( $this->filepath ) && ! empty( $this->local_version ) ) {
			add_filter( "site_transient_update_{$this->type}s", [ $this, 'update_site_transient' ], 20, 1 );
		}

		if ( ! is_multisite() ) {
			add_filter( 'wp_prepare_themes_for_js', [ $this, 'customize_theme_update_html' ] );
		}

		/**
		 * Filter whether to verify FAIR package signatures during update.
		 *
		 * @param bool $verify Whether to verify signatures. Default true.
		 * @return bool
		 */
		if ( apply_filters( 'fair.packages.updater.verify_signatures', true ) ) {
			add_filter( 'upgrader_pre_download', [ $this, 'verify_signature_on_download' ], 10, 4 );
		}

		Packages\add_package_to_release_cache( $this->did );
	}

	/**
	 * Download a package with signature verification.
	 *
	 * @param bool|string|WP_Error $reply      Whether to proceed with the download, the path to the downloaded package, or an existing WP_Error object. Default true.
	 * @param string               $package    The URI of the package. If this is the full path to an existing local file, it will be returned untouched.
	 * @param WP_Upgrader          $upgrader   The WP_Upgrader instance.
	 * @param array                $hook_extra Extra hook data.
	 * @return true|WP_Error True if the signature is valid, otherwise WP_Error.
	 */
	public function verify_signature_on_download( $reply, string $package, WP_Upgrader $upgrader, $hook_extra ) {
		static $has_run = [];

		if ( false !== $reply || ( ! $upgrader instanceof Plugin_Upgrader && ! $upgrader instanceof Theme_Upgrader ) ) {
			return $reply;
		}

		// This method is hooked to 'upgrader_pre_download', which is used in WP_Upgrader::download_package().
		// Bailing on subsequent runs for the same package URI prevents an infinite loop.
		$key = sha1( $this->did . '_' . $package );
		if ( isset( $has_run[ $key ] ) ) {
			return $reply;
		}
		$has_run[ $key ] = true;

		// Local files should be returned untouched.
		if ( ! preg_match( '!^(http|https|ftp)://!i', $package ) && file_exists( $package ) ) {
			return $package;
		}

		$artifact = Packages\pick_artifact_by_lang( $this->release->artifacts->package );
		if ( ! $artifact || $package !== $artifact->url ) {
			return $reply;
		}

		$path = $upgrader->download_package( $package, false, $hook_extra );
		if ( is_wp_error( $path ) ) {
			return $path;
		}

		add_filter( 'wp_trusted_keys', [ $this, 'get_trusted_keys' ], 100 );
		$decoded_base64url = sodium_base642bin( $artifact->signature, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING );
		$result = verify_file_signature( $path, base64_encode( $decoded_base64url ) );
		remove_filter( 'wp_trusted_keys', [ $this, 'get_trusted_keys' ], 100 );

		if ( $result === true ) {
			return $path;
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_Error(
			'fair.packages.signature_verification.failed',
			sprintf(
				/* translators: %s: The package's URL. */
				__( 'Signature verification could not be performed for the package: %s', 'fair' ),
				$package
			)
		);
	}

	/**
	 * Get trusted keys for signature verification.
	 *
	 * @return array
	 */
	public function get_trusted_keys(): array {
		$doc = Packages\get_did_document( $this->did );
		if ( is_wp_error( $doc ) ) {
			return [];
		}

		$keys = $doc->get_fair_signing_keys();
		if ( empty( $keys ) ) {
			return [];
		}

		/*
		 * FAIR uses Base58BTC-encoded Ed25519 keys.
		 * Core expects base64-encoded keys.
		 */
		$recoded_keys = [];
		foreach ( $keys as $key ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$str = Base58BTC::decode( $key->publicKeyMultibase );

			// Ed25519 keys only.
			if ( substr( $str, 0, 2 ) !== "\xed\x01" ) {
				continue;
			}

			$key_material = substr( $str, 2 );
			$recoded_keys[] = base64_encode( $key_material );
		}

		return $recoded_keys;
	}

	/**
	 * Correctly rename dependency for activation.
	 *
	 * @param string      $source        Path of $source.
	 * @param string      $remote_source Path of $remote_source.
	 * @param WP_Upgrader $upgrader      An Upgrader object.
	 * @param array       $hook_extra    Array of hook data.
	 *
	 * @throws TypeError If the type of $upgrader is not correct.
	 *
	 * @return string|WP_Error
	 */
	public function upgrader_source_selection( string $source, string $remote_source, WP_Upgrader $upgrader, $hook_extra = null ) {
		global $wp_filesystem;

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
			if ( isset( $hook_extra['plugin'] ) ) {
				$slug       = dirname( $hook_extra['plugin'] );
				$new_source = trailingslashit( $remote_source ) . $slug;
			}
		}

		// Rename themes.
		if ( $upgrader instanceof Theme_Upgrader ) {
			if ( isset( $hook_extra['theme'] ) ) {
				$slug       = $hook_extra['theme'];
				$new_source = trailingslashit( $remote_source ) . $slug;
			}
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
	public function repo_api_details( $result, string $action, stdClass $response ) {
		if ( "{$this->type}_information" !== $action ) {
			return $result;
		}

		// Exit if not our repo.
		$slug_arr = [ $this->metadata->slug, $this->metadata->slug . '-' . Packages\get_did_hash( $this->did ) ];
		if ( ! in_array( $response->slug, $slug_arr, true ) ) {
			return $result;
		}

		return (object) Packages\get_update_data( $this->did );
	}

	/**
	 * Hook into site_transient_update_{plugins|themes} to update from GitHub.
	 *
	 * @param stdClass $transient Plugin|Theme update transient.
	 *
	 * @return stdClass
	 */
	public function update_site_transient( $transient ) {
		// needed to fix PHP 7.4 warning.
		if ( ! is_object( $transient ) ) {
			$transient = new stdClass();
		}

		$rel_path = plugin_basename( $this->filepath );
		$rel_path = 'theme' === $this->type ? dirname( $rel_path ) : $rel_path;
		$response = Packages\get_update_data( $this->did );
		if ( is_wp_error( $response ) ) {
			return $transient;
		}
		$response = 'plugin' === $this->type ? (object) $response : $response;
		$is_compatible = Packages\check_requirements( $this->release );

		if ( $is_compatible && version_compare( $this->release->version, $this->local_version, '>' ) ) {
			$transient->response[ $rel_path ] = $response;
		} else {
			// Add repo without update to $transient->no_update for 'View details' link.
			$transient->no_update[ $rel_path ] = $response;
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
	public function customize_theme_update_html( $prepared_themes ) {
		$theme = $this->metadata;

		if ( 'theme' !== $this->type ) {
			return $prepared_themes;
		}

		if ( ! empty( $prepared_themes[ $theme->slug ]['hasUpdate'] ) ) {
			$prepared_themes[ $theme->slug ]['update'] = $this->append_theme_actions_content( $theme );
		} else {
			$prepared_themes[ $theme->slug ]['description'] .= $this->append_theme_actions_content( $theme );
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
	protected function append_theme_actions_content( $theme ) {
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
}
