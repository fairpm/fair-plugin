<?php
/*
 * @author   Andy Fragen
 * @license  MIT
 * @link     https://github.com/afragen/git-updater-lite
 * @package  git-updater-lite
 */

namespace Fragen\Git_Updater;

use Plugin_Upgrader;
use stdClass;
use Theme_Upgrader;
use TypeError;
use WP_Error;
use WP_Upgrader;

/**
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'Fragen\\Git_Updater\\Lite' ) ) {
	/**
	 * Class Lite
	 */
	final class Lite {

		// phpcs:disable Generic.Commenting.DocComment.MissingShort
		/** @var string */
		protected $file;

		/** @var string */
		protected $slug;

		/** @var string */
		protected $local_version;

		/** @var stdClass */
		protected $api_data;

		/** @var string */
		protected $update_server;
		// phpcs:enable

		/**
		 * Constructor.
		 *
		 * @param string $file_path File path of plugin/theme.
		 */
		public function __construct( string $file_path ) {
			$this->slug = basename( dirname( $file_path ) );

			if ( str_ends_with( $file_path, 'functions.php' ) ) {
				$this->file = $this->slug . '/style.css';
				$file_path  = dirname( $file_path ) . '/style.css';
			} else {
				$this->file = $this->slug . '/' . basename( $file_path );
			}

			$file_data           = get_file_data(
				$file_path,
				array(
					'Version'   => 'Version',
					'UpdateURI' => 'Update URI',
				)
			);
			$this->local_version = $file_data['Version'];
			$this->update_server = $this->check_update_uri( $file_data['UpdateURI'] );
		}

		/**
		 * Ensure properly formatted Update URI.
		 *
		 * @param string $updateUri Data from Update URI header.
		 *
		 * @return string|WP_Error
		 */
		private function check_update_uri( $updateUri ) {
			if ( filter_var( $updateUri, FILTER_VALIDATE_URL )
				&& null === parse_url( $updateUri, PHP_URL_PATH ) // null means no path is present.
			) {
				$updateUri = untrailingslashit( trim( $updateUri ) );
			} else {
				return new WP_Error( 'invalid_header_data', 'Invalid data from Update URI header', $updateUri );
			}

			return $updateUri;
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
				$php_self = isset( $_SERVER['PHP_SELF'] ) ? sanitize_url( wp_unslash( $_SERVER['PHP_SELF'] ) ) : null;
				if ( null !== $php_self ) {
					// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
					$pagenow = basename( $php_self );
				}
			}

			// Only run on the following pages.
			$pages            = array( 'update-core.php', 'update.php', 'plugins.php', 'themes.php' );
			$view_details     = array( 'plugin-install.php', 'theme-install.php' );
			$autoupdate_pages = array( 'admin-ajax.php', 'index.php', 'wp-cron.php' );
			if ( ! in_array( $pagenow, array_merge( $pages, $view_details, $autoupdate_pages ), true ) ) {
				return;
			}

			if ( empty( $this->update_server ) || is_wp_error( $this->update_server ) ) {
				return new WP_Error( 'invalid_domain', 'Invalid update server domain', $this->update_server );
			}
			$url      = add_query_arg(
				array( 'slug' => $this->slug ),
				sprintf( '%s/wp-json/git-updater/v1/update-api/', $this->update_server )
			);
			$response = get_site_transient( "git-updater-lite_{$this->file}" );
			if ( ! $response ) {
				$response = wp_remote_get( $url );
				if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) === 404 ) {
					return $response;
				}

				$this->api_data = (object) json_decode( wp_remote_retrieve_body( $response ), true );
				if ( null === $this->api_data || empty( (array) $this->api_data ) || property_exists( $this->api_data, 'error' ) ) {
					return new WP_Error( 'non_json_api_response', 'Poorly formed JSON', $response );
				}
				$this->api_data->file = $this->file;

				/*
				* Set transient for 5 minutes as AWS sets 5 minute timeout
				* for release asset redirect.
				*/
				set_site_transient( "git-updater-lite_{$this->file}", $this->api_data, 5 * \MINUTE_IN_SECONDS );
			} else {
				if ( property_exists( $response, 'error' ) ) {
					return new WP_Error( 'repo-no-exist', 'Specified repo does not exist' );
				}
				$this->api_data = $response;
			}

			$this->load_hooks();
		}

		/**
		 * Load hooks.
		 *
		 * @return void
		 */
		public function load_hooks() {
			$type = $this->api_data->type;
			add_filter( 'upgrader_source_selection', array( $this, 'upgrader_source_selection' ), 10, 4 );
			add_filter( "{$type}s_api", array( $this, 'repo_api_details' ), 99, 3 );
			add_filter( "site_transient_update_{$type}s", array( $this, 'update_site_transient' ), 20, 1 );
			if ( ! is_multisite() ) {
				add_filter( 'wp_prepare_themes_for_js', array( $this, 'customize_theme_update_html' ) );
			}

			// Load hook for adding authentication headers for download packages.
			add_filter(
				'upgrader_pre_download',
				function () {
					add_filter( 'http_request_args', array( $this, 'add_auth_header' ), 20, 2 );
					return false; // upgrader_pre_download filter default return value.
				}
			);
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
			if ( "{$this->api_data->type}_information" !== $action ) {
				return $result;
			}

			// Exit if not our repo.
			if ( $response->slug !== $this->api_data->slug ) {
				return $result;
			}

			return $this->api_data;
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

			$response = array(
				'slug'                => $this->api_data->slug,
				$this->api_data->type => 'theme' === $this->api_data->type ? $this->api_data->slug : $this->api_data->file,
				'url'                 => isset( $this->api_data->url ) ? $this->api_data->url : $this->api_data->slug,
				'icons'               => (array) $this->api_data->icons,
				'banners'             => $this->api_data->banners,
				'branch'              => $this->api_data->branch,
				'type'                => "{$this->api_data->git}-{$this->api_data->type}",
				'update-supported'    => true,
				'requires'            => $this->api_data->requires,
				'requires_php'        => $this->api_data->requires_php,
				'new_version'         => $this->api_data->version,
				'package'             => $this->api_data->download_link,
				'tested'              => $this->api_data->tested,
			);
			if ( 'theme' === $this->api_data->type ) {
				$response['theme_uri'] = $response['url'];
			}

			if ( version_compare( $this->api_data->version, $this->local_version, '>' ) ) {
				$response                    = 'plugin' === $this->api_data->type ? (object) $response : $response;
				$key                         = 'plugin' === $this->api_data->type ? $this->api_data->file : $this->api_data->slug;
				$transient->response[ $key ] = $response;
			} else {
				$response = 'plugin' === $this->api_data->type ? (object) $response : $response;

				// Add repo without update to $transient->no_update for 'View details' link.
				$transient->no_update[ $this->api_data->file ] = $response;
			}

			return $transient;
		}

		/**
		 * Add auth header for download package.
		 *
		 * @param array  $args Array of http args.
		 * @param string $url  Download URL.
		 *
		 * @return array
		 */
		public function add_auth_header( $args, $url ) {
			if ( property_exists( $this->api_data, 'auth_header' )
				&& str_contains( $url, $this->api_data->slug )
			) {
				$args = array_merge( $args, $this->api_data->auth_header );
			}
			return $args;
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
			$theme = $this->api_data;

			if ( 'theme' !== $theme->type ) {
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
					array(
						'tab'       => 'theme-information',
						'theme'     => $theme->slug,
						'TB_iframe' => 'true',
						'width'     => 270,
						'height'    => 400,
					),
					self_admin_url( 'theme-install.php' )
				)
			);
			$nonced_update_url = wp_nonce_url(
				esc_attr(
					add_query_arg(
						array(
							'action' => 'upgrade-theme',
							'theme'  => rawurlencode( $theme->slug ),
						),
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
}
