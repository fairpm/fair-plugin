<?php
/**
 * Packages Admin Information.
 *
 * @package FAIR
 */

namespace FAIR\Packages\Admin\Info;

use FAIR\Packages;
use FAIR\Packages\Admin;
use FAIR\Packages\DID\Document as DIDDocument;
use FAIR\Packages\MetadataDocument;
use FAIR\Packages\ReleaseDocument;
use FAIR\Updater;
use WP_Error;

/**
 * Sanitize HTML content for plugin information.
 *
 * @param string $html The HTML content to sanitize.
 * @return string Sanitized HTML content.
 */
function sanitize_html( string $html ) : string {
	static $allowed = [
		'a' => [
			'href' => [],
			'title' => [],
			'target' => [],
		],
		'abbr' => [
			'title' => [],
		],
		'acronym' => [
			'title' => [],
		],
		'code' => [],
		'pre' => [],
		'em' => [],
		'strong' => [],
		'div' => [
			'class' => [],
		],
		'span' => [
			'class' => [],
		],
		'p' => [],
		'br' => [],
		'ul' => [],
		'ol' => [],
		'li' => [],
		'h1' => [],
		'h2' => [],
		'h3' => [],
		'h4' => [],
		'h5' => [],
		'h6' => [],
		'img' => [
			'src' => [],
			'class' => [],
			'alt' => [],
		],
		'blockquote' => [
			'cite' => true,
		],
	];
	return wp_kses( $html, $allowed );
}

/**
 * Get section title.
 *
 * @param  string $id DID.
 *
 * @return string
 */
function get_section_title( string $id ) {
	switch ( $id ) {
		case 'description':
			return _x( 'Description', 'Plugin installer section title', 'fair' );
		case 'installation':
			return _x( 'Installation', 'Plugin installer section title', 'fair' );
		case 'faq':
			return _x( 'FAQ', 'Plugin installer section title', 'fair' );
		case 'screenshots':
			return _x( 'Screenshots', 'Plugin installer section title', 'fair' );
		case 'changelog':
			return _x( 'Changelog', 'Plugin installer section title', 'fair' );
		case 'reviews':
			return _x( 'Reviews', 'Plugin installer section title', 'fair' );
		case 'other_notes':
			return _x( 'Other Notes', 'Plugin installer section title', 'fair' );
		default:
			return ucwords( str_replace( '_', ' ', $id ) );
	}
}

/**
 * Render page.
 *
 * @param  MetadataDocument $metadata Metadata for page render.
 * @param  string           $tab Page tab.
 * @param  string           $section Page section.
 *
 * @return void
 */
function render_page( MetadataDocument $metadata, string $tab, string $section ) {
	iframe_header( __( 'Plugin Installation', 'fair' ) );
	render( $metadata, $tab, $section );
	wp_print_request_filesystem_credentials_modal();
	wp_print_admin_notice_templates();

	iframe_footer();
}

/**
 * Displays plugin information in dialog box form.
 *
 * @since 2.7.0
 *
 * @global string $tab
 * @param  MetadataDocument $doc Metadata for page render.
 * @param  string           $tab Page tab.
 * @param  string           $section Page section.
 */
function render( MetadataDocument $doc, string $tab, string $section ) {
	$sections = (array) $doc->sections;

	if ( ! isset( $sections[ $section ] ) && ! empty( $sections ) ) {
		$section = array_keys( $sections )[0];
	}

	$releases = array_values( $doc->releases );
	usort( $releases, fn ( $a, $b ) => version_compare( $b->version, $a->version ) );
	$latest = ! empty( $releases ) ? reset( $releases ) : null;

	// Add banners, if available.
	$_with_banner = '';
	if ( ! empty( $latest->artifacts->banner ) ) {
		$_with_banner = 'with-banner';
		render_banner( $latest );
	}

	?>
	<div id="plugin-information-scrollable">
		<div id="<?= esc_attr( $tab ); ?>-title" class="<?= esc_attr( $_with_banner ); ?>">
			<div class='vignette'></div>
			<h2><?= esc_html( $doc->name ); ?></h2>
		</div>
		<div id="<?= esc_attr( $tab ); ?>-tabs" class="<?= esc_attr( $_with_banner ); ?>">
			<?php
			foreach ( $sections as $section_id => $content ) :
				$href = add_query_arg( [
					'tab'     => $tab,
					'section' => $section_id,
				] );
				?>
				<a
					name="<?= esc_attr( $section_id ); ?>"
					href="<?= esc_url( $href ); ?>"
					<?= ( $section_id === $section ) ? ' class="current"' : ''; ?>
				><?= esc_html( get_section_title( $section_id ) ); ?></a>
				<?php
			endforeach;
			?>
		</div>
		<div id="<?= esc_attr( $tab ); ?>-content" class="<?= esc_attr( $_with_banner ); ?>">
			<?php render_fyi( $doc, $latest ) ?>

			<div id="section-holder">
			<?php
			add_requirement_notices( $latest );

			// For backwards compatibility.
			do_action_deprecated( 'minifair.render.notices', [ $doc, $tab, $section ], '1.2', 'fair_beacon.render.notices' );

			do_action( 'fair_beacon.render.notices', $doc, $tab, $section );

			foreach ( $sections as $section_id => $content ) {
				$prepared = sanitize_html( $content );
				$prepared = links_add_target( $prepared, '_blank' );

				printf(
					'<div id="section-%s" class="section" style="display: %s;">%s</div>',
					esc_attr( $section_id ),
					( $section_id === $section ) ? 'block' : 'none',
					// phpcs:ignore HM.Security.EscapeOutput.OutputNotEscaped -- sanitize_html() is a custom wrapper for wp_kses().
					sanitize_html( $prepared )
				);
			}
			?>
			</div>
		</div>
	</div>

	<div id="<?= esc_attr( $tab ); ?>-footer">
		<?php
		if ( current_user_can( 'install_plugins' ) || current_user_can( 'update_plugins' ) ) {
			$data = [];
			$button = get_action_button( $doc, $latest );
			$button = str_replace( 'class="', 'class="right ', $button );

			if ( ! str_contains( $button, _x( 'Activate', 'plugin', 'fair' ) ) ) {
				// todo: requires changes to the JS to catch the DID.
				// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
				// $button = str_replace( 'class="', 'id="plugin_install_from_iframe" class="', $button );
			}

			echo wp_kses_post( $button );
		}
		?>
	</div>
	<?php
}

/**
 * Render banner.
 *
 * @param  ReleaseDocument $release Release document.
 *
 * @return void
 */
function render_banner( ReleaseDocument $release ) {
	if ( empty( $release->artifacts->banner ) ) {
		return;
	}

	$banners = $release->artifacts->banner;

	$regular = array_find( $banners, fn ( $banner ) => $banner->width === 772 && $banner->height === 250 );
	$high_res = array_find( $banners, fn ( $banner ) => $banner->width === 1544 && $banner->height === 500 );
	if ( empty( $regular ) && empty( $high_res ) ) {
		return;
	}

	?>
	<style type="text/css">
		#plugin-information-title.with-banner {
			background-image: url( <?php echo esc_url( $regular->url ?? $high_res->url ); ?> );
		}
		@media only screen and ( -webkit-min-device-pixel-ratio: 1.5 ) {
			#plugin-information-title.with-banner {
				background-image: url( <?php echo esc_url( $high_res->url ?? $regular->url ); ?> );
			}
		}
	</style>
	<?php
}

/**
 * Name requirement.
 *
 * @param  string $requirement The requirement.
 *
 * @return string
 */
function name_requirement( string $requirement ) : string {
	switch ( true ) {
		case ( $requirement === 'env:wp' ):
			return __( 'WordPress', 'fair' );

		case ( $requirement === 'env:php' ):
			return __( 'PHP', 'fair' );

		case str_starts_with( $requirement, 'env:php-' ):
			return substr( $requirement, 8 );

		default:
			return $requirement;
	}
}

/**
 * Render FYI.
 *
 * @param  MetadataDocument $doc Metadata document.
 * @param  ReleaseDocument  $release Release document.
 *
 * @return void
 */
function render_fyi( MetadataDocument $doc, ReleaseDocument $release ) : void {
	$did = Packages\get_did_document( $doc->id );
	?>
	<div class="fyi">
		<ul>
			<li><?php render_alias_notice( $did ); ?></li>
			<?php if ( ! empty( $release ) ) : ?>
				<li><strong><?= __( 'Version:', 'fair' ); ?></strong> <?= esc_attr( $release->version ); ?></li>
			<?php endif; ?>
			<?php if ( ! empty( $doc->slug ) ) : ?>
				<li><strong><?= __( 'Slug:', 'fair' ); ?></strong> <?= esc_attr( $doc->slug ); ?></li>
			<?php endif; ?>
			<?php if ( ! empty( $doc->id ) ) : ?>
				<li><strong><?= __( 'ID:', 'fair' ); ?></strong> <code><?= esc_attr( $doc->id ); ?></code></li>
			<?php endif; ?>
			<?php if ( ! empty( $release->requires ) ) : ?>
				<li>
					<strong><?= __( 'Requires:', 'fair' ); ?></strong>
					<ul>
						<?php foreach ( (array) $release->requires as $type => $constraint ) : ?>
							<li><?= esc_html( name_requirement( $type ) ); ?> <?= esc_html( $constraint ); ?></li>
						<?php endforeach; ?>
					</ul>
				</li>
			<?php endif; ?>
			<?php if ( ! empty( $release->suggests ) ) : ?>
				<li>
					<strong><?= __( 'Suggests:', 'fair' ); ?></strong>
					<ul>
						<?php foreach ( (array) $release->suggests as $type => $constraint ) : ?>
							<li><?= esc_html( name_requirement( $type ) ); ?> <?= esc_html( $constraint ); ?></li>
						<?php endforeach; ?>
					</ul>
				</li>
			<?php endif; ?>
		</ul>
		<?php
		if ( ! empty( $doc->authors ) ) :
			?>
			<h3><?= __( 'Authors', 'fair' ); ?></h3>
			<ul class="contributors">
				<?php
				foreach ( (array) $doc->authors as $author ) {
					if ( empty( $author->name ) ) {
						continue;
					}
					$url = $author->url ?? ( isset( $author->email ) ? 'mailto:' . $author->email : null );
					printf(
						'<a href="%s" target="_blank">%s</a>',
						esc_url( $url ),
						esc_html( $author->name )
					);
					break;
				}
				?>
			</ul>
		<?php endif ?>
		<?php
		$repo_host = get_repository_hostname( $doc->id );
		if ( $repo_host ) :
			?>
			<p><small>Plugin available via FAIR repository hosted at <?php echo esc_html( $repo_host ); ?></small></p>
		<?php else : ?>
			<p><small>Plugin available via FAIR repository</small></p>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Get the hostname for the repository hosting a package.
 *
 * @param string $did DID to check.
 * @return string|null Hostname if available, null if there's an error.
 */
function get_repository_hostname( string $did ) : ?string {
	$did_doc = Packages\get_did_document( $did );
	if ( is_wp_error( $did_doc ) ) {
		return null;
	}

	$repo = $did_doc->get_service( Packages\SERVICE_ID );
	if ( empty( $repo ) ) {
		return null;
	}

	// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	$host = parse_url( $repo->serviceEndpoint, PHP_URL_HOST );
	if ( empty( $host ) ) {
		// Invalid URL.
		return null;
	}

	return $host;
}

/**
 * Check requirements, and add notices if not met.
 *
 * @param ReleaseDocument $release Release document.
 * @return void
 */
function add_requirement_notices( ReleaseDocument $release ) : void {
	$unmet_requires = Packages\get_unmet_requirements( (array) $release->requires );
	$unmet_suggests = Packages\get_unmet_requirements( (array) $release->suggests );
	if ( empty( $unmet_requires ) && empty( $unmet_suggests ) ) {
		return;
	}

	if ( isset( $unmet_requires['env:php'] ) ) {
		$compatible_php_notice_message  = '<p>';
		$compatible_php_notice_message .= __( '<strong>Error:</strong> This plugin <strong>requires a newer version of PHP</strong>.', 'fair' );

		if ( current_user_can( 'update_php' ) ) {
			$compatible_php_notice_message .= sprintf(
				/* translators: %s: URL to Update PHP page. */
				' ' . __( '<a href="%s" target="_blank">Click here to learn more about updating PHP</a>.', 'fair' ),
				esc_url( wp_get_update_php_url() )
			) . wp_update_php_annotation( '</p><p><em>', '</em>', false );
		} else {
			$compatible_php_notice_message .= '</p>';
		}

		wp_admin_notice(
			$compatible_php_notice_message,
			[
				'type'               => 'error',
				'additional_classes' => [ 'notice-alt' ],
				'paragraph_wrap'     => false,
			]
		);
	}

	$is_dev = (bool) preg_match( '/alpha|beta|RC/', get_bloginfo( 'version' ) );
	if ( isset( $unmet_suggests['env:wp'] ) && ! $is_dev ) {
		wp_admin_notice(
			__( '<strong>Warning:</strong> This plugin <strong>has not been tested</strong> with your current version of WordPress.', 'fair' ),
			[
				'type'               => 'warning',
				'additional_classes' => [ 'notice-alt' ],
			]
		);
	} elseif ( isset( $unmet_requires['env:wp'] ) ) {
		$compatible_wp_notice_message = __( '<strong>Error:</strong> This plugin <strong>requires a newer version of WordPress</strong>.', 'fair' );
		if ( current_user_can( 'update_core' ) ) {
			$compatible_wp_notice_message .= sprintf(
				/* translators: %s: URL to WordPress Updates screen. */
				' ' . __( '<a href="%s" target="_parent">Click here to update WordPress</a>.', 'fair' ),
				esc_url( self_admin_url( 'update-core.php' ) )
			);
		}

		wp_admin_notice(
			$compatible_wp_notice_message,
			[
				'type'               => 'error',
				'additional_classes' => [ 'notice-alt' ],
			]
		);
	}
}

/**
 * Render the validation notice.
 *
 * Renders the validation status for the package's alias. Also returns a bool
 * indicating whether the package is "safe" to install - packages which fail
 * validation are not safe, while those without an alias or with a valid alias
 * are safe.
 *
 * @param DIDDocument $did DID to validate.
 * @return bool True if the package is "safe" to install, false if install should be blocked.
 */
function render_alias_notice( DIDDocument $did ) : bool {
	$validation = Packages\validate_package_alias( $did );
	$title = __( 'Domain Alias:', 'fair' );
	$result = false;
	switch ( gettype( $validation ) ) {
		case 'string':
			$link = sprintf(
				'<a href="%s" target="_blank">%s</a>',
				esc_url( 'https://' . $validation ),
				esc_html( $validation )
			);
			$message = sprintf(
				/* translators: %s: URL for validated domain */
				__( '<strong>Validated</strong> as %s', 'fair' ),
				$link
			);
			$result = true;
			break;

		case 'NULL':
			$message = __( 'Not validated: No domain alias is set', 'fair' );
			$result = true;
			break;

		default:
			if ( ! is_wp_error( $validation ) ) {
				// Invalid type, assume failure.
				$validation = new WP_Error(
					'fair.packages.admin.info.validation_notice.invalid_result',
					__( 'An unknown error occurred', 'fair' )
				);
			}
			$message = sprintf(
				'<strong>%s</strong>',
				esc_html__( 'Validation failed', 'fair' )
			);

			// For backwards compatibility.
			do_action_deprecated( 'minifair.render.notices', [ $validation ], '1.2', 'fair_beacon.render.notices' );

			add_action( 'fair_beacon.render.notices', function () use ( $validation ) {
				wp_admin_notice(
					sprintf(
						/* translators: %s: validation error message */
						__( '<p><strong>Error:</strong> Failed domain alias validation, this package may be unsafe: %s</p>', 'fair' ),
						esc_html( $validation->get_error_message() )
					),
					[
						'type'               => 'error',
						'additional_classes' => [ 'notice-alt' ],
						'paragraph_wrap'     => false,
					]
				);
			} );
			$result = false;
			break;
	}

	printf(
		'<strong>%s</strong> %s',
		esc_html( $title ),
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized.
		sanitize_html( $message )
	);
	return $result;
}

/**
 * Gets the markup for the plugin install action button.
 *
 * @param  MetadataDocument $doc Metadata document.
 * @param  ReleaseDocument  $release Release document.
 *
 * @return string The markup for the dependency row button. An empty string if the user does not have capabilities.
 */
function get_action_button( MetadataDocument $doc, ReleaseDocument $release ) {
	if ( ! current_user_can( 'install_plugins' ) && ! current_user_can( 'update_plugins' ) ) {
		// How did you get here, pal?
		return '';
	}

	$type = str_replace( 'wp-', '', $doc->type );
	$installed_version = Packages\get_installed_version( $doc->id, $type );
	if ( $installed_version === null ) {
		$status = 'install';
	} elseif ( version_compare( $installed_version, $release->version, '<' ) ) {
		$status = 'update';
	} else {
		$status = 'installed';
	}

	if ( $status === 'install' ) {
		$file = null;
		$slug = null;
	} else {
		$file = Updater\get_packages()[ "{$type}s" ][ $doc->id ];
		$file = $type === 'plugin' ? plugin_basename( $file ) : basename( dirname( $file ) );
		$slug = $type === 'plugin' ? dirname( $file ) : $file;
	}

	// Do we actually meet the requirements?
	$compatible = Packages\check_requirements( $release );
	switch ( $status ) {
		case 'install':
			if ( ! $compatible ) {
				return sprintf(
					'<button type="button" class="install-now button button-disabled" disabled="disabled">%s</button>',
					esc_html__( 'Install Now', 'fair' )
				);
			}

			$slug = $doc->slug . '-' . str_replace( ':', '--', $doc->id );
			return sprintf(
				'<a id="plugin_install_from_iframe" class="install-now button" data-id="%s" href="%s" data-slug="%s" aria-label="%s" data-name="%s" role="button">%s</a>',
				esc_attr( $doc->id ),
				esc_url( Admin\get_direct_install_url( $doc, $release ) ),
				esc_attr( $slug ),
				/* translators: %s: The package's name. */
				esc_attr( sprintf( __( 'Install %s now', 'fair' ), $doc->name ) ),
				esc_attr( $doc->name ),
				esc_html__( 'Install Now', 'fair' )
			);

		case 'update':
			if ( ! $compatible ) {
				return sprintf(
					'<button type="button" class="update-now button button-disabled" disabled="disabled">%s</button>',
					esc_html__( 'Update Now', 'fair' )
				);
			}

			return sprintf(
				'<a id="plugin_install_from_iframe" class="update-now button" data-id="%s" data-%s="%s" data-slug="%s" href="%s" aria-label="%s" data-name="%s" role="button">%s</a>',
				esc_attr( $doc->id ),
				esc_attr( $type ),
				esc_attr( $file ),
				esc_attr( $slug ),
				esc_url( Admin\get_direct_update_url( $doc ) ),
				/* translators: %s: The package's name. */
				esc_attr( sprintf( __( 'Update %s now', 'fair' ), $doc->name ) ),
				esc_attr( $doc->name ),
				esc_html__( 'Update Now', 'fair' )
			);

		case 'installed':
			if ( current_user_can( 'activate_plugin', $file ) && is_plugin_inactive( $file ) ) {
				return sprintf(
					'<a href="%s" class="button activate-now button-primary" aria-label="%s" data-name="%s">%s</a>',
					esc_url( wp_nonce_url( self_admin_url( 'plugins.php?action=activate&plugin=' . rawurlencode( $file ) ), 'activate-plugin_' . $file ) ),
					/* translators: %s: The package's name. */
					esc_attr( sprintf( __( 'Activate %s now', 'fair' ), $doc->name ) ),
					esc_attr( $doc->name ),
					esc_html__( 'Activate', 'fair' )
				);
			}

			return sprintf(
				'<button type="button" class="button button-disabled" disabled="disabled">%s</button>',
				esc_html__( 'Installed', 'fair' )
			);
	}
}
