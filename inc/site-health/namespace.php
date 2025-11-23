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

/**
 * Enqueue scripts for Site Health.
 *
 * @param string $hook_suffix Hook to identify current screen.
 */
function enqueue_media_scripts( $hook_suffix ) {

	if ( 'site-health.php' !== $hook_suffix ) {
		return;
	}

	wp_enqueue_script( 'fair-site-health', esc_url( plugin_dir_url( \FAIR\PLUGIN_FILE ) . 'assets/js/fair-site-health.js' ), [ 'wp-i18n' ], \FAIR\VERSION, true );
	wp_localize_script( 'fair-site-health', 'fairSiteHealth',
		[
			'defaultRepoDomain' => \FAIR\Default_Repo\get_default_repo_domain(),
			'repoIPAddress'     => gethostbyname( \FAIR\Default_Repo\get_default_repo_domain() ),
			'errorMessageRegex' => build_error_message_regex(),
		]
	);

}

/**
 * Set up regular expression used for handling error messages.
 *
 * @return string
 */
function build_error_message_regex() {
	$regex = str_replace(
		[ '%1\$s', '%2\$s' ],
		[ '(?:.*?)', '(.*)' ],
		preg_quote( __( 'Your site is unable to reach WordPress.org at %1$s, and returned the error: %2$s' ) ) // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
	);
	$regex = $regex . '<\/p>';

	return $regex;
}