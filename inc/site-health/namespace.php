<?php
/**
 * Implements the plugin settings page.
 *
 * @package FAIR
 */

namespace FAIR\Site_Health;

/**
 * Bootstrap.
 */
function bootstrap() {
	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_media_scripts' );
}

function enqueue_media_scripts( $hook_suffix ) {

	if ( 'site-health.php' !== $hook_suffix ) {
		return;
	}

	wp_enqueue_script( 'fair-site-health', esc_url( plugin_dir_url( \FAIR\PLUGIN_FILE ) . 'assets/js/fair-site-health.js' ), [ 'wp-i18n' ], \FAIR\VERSION, true );
	wp_localize_script( 'fair-site-health', 'fairSiteHealth',
		[
			'defaultRepoDomain' => \FAIR\Default_Repo\get_default_repo_domain(),
			'repoIPAddress' => gethostbyname( \FAIR\Default_Repo\get_default_repo_domain() ),
		]
	);

}

