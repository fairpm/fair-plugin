<?php

namespace FAIR\Packages\Admin\Info;

use FAIR\Packages\Admin;
use FAIR\Packages\MetadataDocument;
use FAIR\Packages\ReleaseDocument;

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

function get_section_title( string $id ) {
	switch ( $id ) {
		case 'description':
			return _x( 'Description', 'Plugin installer section title' );
		case 'installation':
			return _x( 'Installation', 'Plugin installer section title' );
		case 'faq':
			return _x( 'FAQ', 'Plugin installer section title' );
		case 'screenshots':
			return _x( 'Screenshots', 'Plugin installer section title' );
		case 'changelog':
			return _x( 'Changelog', 'Plugin installer section title' );
		case 'reviews':
			return _x( 'Reviews', 'Plugin installer section title' );
		case 'other_notes':
			return _x( 'Other Notes', 'Plugin installer section title' );
		default:
			return ucwords( str_replace( '_', ' ', $id ) );
	}
}

function render_page( MetadataDocument $metadata, string $tab, string $section ) {
	iframe_header( __( 'Plugin Installation' ) );
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
 */
function render( MetadataDocument $doc, string $tab, string $section ) {
	$sections = (array) $doc->sections;

	if ( ! isset( $sections[ $section ] ) ) {
		$section = array_keys( $sections )[0];
	}

	$releases = $doc->releases;
	usort( $releases, fn ( $a, $b ) => version_compare( $b->version, $a->version ) );
	$latest = ! empty( $releases ) ? reset( $releases ) : null;

	$_with_banner = '';

	?>
	<div id="plugin-information-scrollable">
		<div id="<?= esc_attr( $tab ) ?>-title" class="<?= esc_attr( $_with_banner ) ?>">
			<div class='vignette'></div>
			<h2><?= esc_html( $doc->name ) ?></h2>
		</div>
		<div id="<?= esc_attr( $tab ) ?>-tabs" class="<?= esc_attr( $_with_banner ) ?>">
			<?php
			foreach ( $sections as $section_id => $content ):
				$class = ( $section_id === $section ) ? ' class="current"' : '';
				$href = add_query_arg( [
					'tab'     => $tab,
					'section' => $section_id,
				] );
				?>
				<a
					name="<?= esc_attr( $section_id ) ?>"
					href="<?= esc_url( $href ) ?>"
					<?= $class ?>
				><?= esc_html( get_section_title( $section_id ) ) ?></a>
			<?php
			endforeach;
			?>
		</div>
		<div id="<?= $tab; ?>-content" class="<?= esc_attr( $_with_banner ); ?>">
			<?php render_fyi( $doc, $latest ) ?>

			<div id="section-holder">
			<?php
			// check_requirements( $latest );
			foreach ( $sections as $section_id => $content ) {
				$prepared = sanitize_html( $content );
				$prepared = links_add_target( $prepared, '_blank' );

				printf(
					'<div id="section-%s" class="section" style="display: %s;">%s</div>',
					esc_attr( $section_id ),
					( $section_id === $section ) ? 'block' : 'none',
					$prepared
				);
			}
			?>
			</div>
		</div>
	</div>

	<div id="<?= esc_attr( $tab ) ?>-footer">
		<?php
		if ( current_user_can( 'install_plugins' ) || current_user_can( 'update_plugins' ) ) {
			$data = [];
			$button = get_action_button( $doc, $latest );
			$button = str_replace( 'class="', 'class="right ', $button );

			if ( ! str_contains( $button, _x( 'Activate', 'plugin' ) ) ) {
				// todo: requires changes to the JS to catch the DID.
				// $button = str_replace( 'class="', 'id="plugin_install_from_iframe" class="', $button );
			}

			echo wp_kses_post( $button );
		}
		?>
	</div>
	<?php
}

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

function render_fyi( MetadataDocument $doc, ReleaseDocument $release ) : void {
	?>
	<div class="fyi">
		<ul>
			<?php if ( ! empty( $release ) ) : ?>
				<li><strong><?= __( 'Version:' ); ?></strong> <?= $release->version; ?></li>
			<?php endif; ?>
			<?php if ( ! empty( $doc->slug ) ) : ?>
				<li><strong><?= __( 'Slug:' ); ?></strong> <?= $doc->slug; ?></li>
			<?php endif; ?>
			<?php if ( ! empty( $release->requires ) ) : ?>
				<li>
					<strong><?= __( 'Requires:' ); ?></strong>
					<ul>
						<?php foreach ( (array) $release->requires as $type => $constraint ) : ?>
							<li><?= esc_html( name_requirement( $type ) ); ?> <?= esc_html( $constraint ); ?></li>
						<?php endforeach; ?>
					</ul>
				</li>
			<?php endif; ?>
			<?php if ( ! empty( $release->suggests ) ) : ?>
				<li>
					<strong><?= __( 'Suggests:' ); ?></strong>
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
			<h3><?= __( 'Authors' ); ?></h3>
			<ul class="contributors">
				<?php
				foreach ( (array) $doc->authors as $author ) {
					if ( empty( $author->name ) ) {
						continue;
					}
					$url = $author->url ?? ( $author->email ? 'mailto:' . $author->email : null );
					printf(
						'<a href="%s" target="_blank">%s</a>',
						esc_url( $url ),
						esc_html( $author->name )
					);
				}
				?>
			</ul>
		<?php endif ?>
	</div>
	<?php
}

function check_requirements( ReleaseDocument $release ) {
	$requires_php = isset( $api->requires_php ) ? $api->requires_php : null;
	$requires_wp  = isset( $api->requires ) ? $api->requires : null;

	$compatible_php = is_php_version_compatible( $requires_php );
	$compatible_wp  = is_wp_version_compatible( $requires_wp );
	$tested_wp      = ( empty( $api->tested ) || version_compare( get_bloginfo( 'version' ), $api->tested, '<=' ) );

	if ( ! $compatible_php ) {
		$compatible_php_notice_message  = '<p>';
		$compatible_php_notice_message .= __( '<strong>Error:</strong> This plugin <strong>requires a newer version of PHP</strong>.' );

		if ( current_user_can( 'update_php' ) ) {
			$compatible_php_notice_message .= sprintf(
				/* translators: %s: URL to Update PHP page. */
				' ' . __( '<a href="%s" target="_blank">Click here to learn more about updating PHP</a>.' ),
				esc_url( wp_get_update_php_url() )
			) . wp_update_php_annotation( '</p><p><em>', '</em>', false );
		} else {
			$compatible_php_notice_message .= '</p>';
		}

		wp_admin_notice(
			$compatible_php_notice_message,
			array(
				'type'               => 'error',
				'additional_classes' => array( 'notice-alt' ),
				'paragraph_wrap'     => false,
			)
		);
	}

	if ( ! $tested_wp ) {
		wp_admin_notice(
			__( '<strong>Warning:</strong> This plugin <strong>has not been tested</strong> with your current version of WordPress.' ),
			array(
				'type'               => 'warning',
				'additional_classes' => array( 'notice-alt' ),
			)
		);
	} elseif ( ! $compatible_wp ) {
		$compatible_wp_notice_message = __( '<strong>Error:</strong> This plugin <strong>requires a newer version of WordPress</strong>.' );
		if ( current_user_can( 'update_core' ) ) {
			$compatible_wp_notice_message .= sprintf(
				/* translators: %s: URL to WordPress Updates screen. */
				' ' . __( '<a href="%s" target="_parent">Click here to update WordPress</a>.' ),
				esc_url( self_admin_url( 'update-core.php' ) )
			);
		}

		wp_admin_notice(
			$compatible_wp_notice_message,
			array(
				'type'               => 'error',
				'additional_classes' => array( 'notice-alt' ),
			)
		);
	}
}

/**
 * Gets the markup for the plugin install action button.
 *
 * @return string The markup for the dependency row button. An empty string if the user does not have capabilities.
 */
function get_action_button( MetadataDocument $doc, ReleaseDocument $release ) {
	if ( ! current_user_can( 'install_plugins' ) && ! current_user_can( 'update_plugins' ) ) {
		// How did you get here, pal?
		return '';
	}

	// Do we actually meet the requirements?
	// $compatible = check_requirements( $release );
	$compatible = true;

	$status = 'install'; // todo
	switch ( $status ) {
		case 'install':
			if ( ! $compatible ) {
				return sprintf(
					'<button type="button" class="install-now button button-disabled" disabled="disabled">%s</button>',
					esc_html_x( 'Install Now', 'fair' )
				);
			}

			return sprintf(
				'<a class="install-now button" data-id="%s" href="%s" aria-label="%s" data-name="%s" role="button">%s</a>',
				esc_attr( $doc->id ),
				esc_url( Admin\get_direct_install_url( $doc ) ),
				/* translators: %s: Plugin name and version. */
				esc_attr( sprintf( _x( 'Install %s now', 'plugin' ), $doc->name ) ),
				esc_attr( $doc->name ),
				esc_html_x( 'Install Now', 'plugin' )
			);

		default:
			// todo
	}
}
