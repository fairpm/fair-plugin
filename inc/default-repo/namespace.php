<?php
/**
 * Replaces calls to the WordPress.org API with calls to the API of the chosen FAIR repository.
 *
 * @package FAIR
 */

namespace FAIR\Default_Repo;

use FAIR;

/**
 * Bootstrap.
 */
function bootstrap() {
	add_filter( 'pre_http_request', __NAMESPACE__ . '\\replace_repo_api_urls', 100, 3 );
	add_filter( 'install_plugins_tabs', __NAMESPACE__ . '\\remove_favorites_tab' );
}

/**
 * Get the default repository domain.
 *
 * This allows for hosts or users to override the default to a local mirror.
 *
 * @return string The default repository domain.
 */
function get_default_repo_domain() : string {
	if ( defined( 'FAIR_DEFAULT_REPO_DOMAIN' ) ) {
		return FAIR_DEFAULT_REPO_DOMAIN;
	}

	return 'api.aspirecloud.net';
}

/**
 * Replace the repository API URLs.
 *
 * Replaces api.wordpress.org with the repository we're using, for the plugins
 * and themes APIs. Only these get passed to the chosen FAIR repo, as the others are
 * handled in other modules.
 *
 * @param bool|array $status Filtered value, or false to proceed.
 * @param array $args HTTP request arguments.
 * @param string $url The request URL.
 * @return bool|array Replaced value, or false to proceed.
 */
function replace_repo_api_urls( $status, $args, $url ) {
	static $is_replacing = false;
	if ( $is_replacing ) {
		return $status;
	}

	if (
		! str_contains( $url, 'api.wordpress.org/plugins/' )
		&& ! str_contains( $url, 'api.wordpress.org/themes/' )
		&& ! str_contains( $url, 'api.wordpress.org/core/version-check/' )
	) {
		return $status;
	}

	// Shortcircuit if the user has explicitly chosen to use api.wordpress.org.
	if ( get_default_repo_domain() === 'api.wordpress.org' ) {
		return $status;
	}

	// Alter the URL, then reissue the request (with a lock to prevent loops).
	$url = str_replace( '//api.wordpress.org/', '//' . get_default_repo_domain() . '/', $url );

	// Indicate this is a FAIR install.
	$url = add_query_arg( '_fair', FAIR\VERSION, $url );

	$is_replacing = true;
	$response = wp_remote_request( $url, $args );
	$is_replacing = false;
	return $response;
}

/**
 * Remove the Favorites tab from the plugin browser.
 *
 * This tab only makes sense for WordPress.org, so is not supported by
 * other repositories.
 *
 * @param array $tabs Tabs in the plugin browser.
 */
function remove_favorites_tab( array $tabs ) : array {
	unset( $tabs['favorites'] );
	return $tabs;
}
