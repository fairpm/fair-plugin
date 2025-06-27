<?php
/**
 * Prevents calls to the WordPress.org translations API.
 *
 * @package FAIR
 */

namespace FAIR\Translations_Api;

use WP_Error;

/**
 * Bootstrap.
 */
function bootstrap() {
	if ( defined( 'FAIR_TRANSLATIONS_API_DOMAIN' ) && FAIR_TRANSLATIONS_API_DOMAIN ) {
		add_filter( 'translations_api', __NAMESPACE__ . '\\translations_api', 10, 3 );
	}
}

/**
 * Get translations from the FAIR Translations API.
 * Copied from the WordPress.org translations API, modified to use a custom domain.
 * Will be modified in the future, after the FAIR Translations API is implemented.
 */
function translations_api( $result, $type, $args ) {
	if ( ! defined( 'FAIR_TRANSLATIONS_API_DOMAIN' ) || ! FAIR_TRANSLATIONS_API_DOMAIN ) {
		return $result;
	}

	$domain = trim( FAIR_TRANSLATIONS_API_DOMAIN, '/' );

	$url = 'http://' . $domain . '/translations/' . $type . '/1.0/';
	$http_url = $url;

	$ssl = wp_http_supports( array( 'ssl' ) );
	if ( $ssl ) {
		$url = set_url_scheme( $url, 'https' );
	}

	$options = array(
		'timeout' => 3,
		'body'    => array(
			'wp_version' => wp_get_wp_version(),
			'locale'     => get_locale(),
			'version'    => $args['version'], // Version of plugin, theme or core.
		),
	);

	if ( 'core' !== $type ) {
		$options['body']['slug'] = $args['slug']; // Plugin or theme slug.
	}

	$request = wp_remote_post( $url, $options );
	if ( $ssl && is_wp_error( $request ) ) {
		wp_trigger_error(
			__FUNCTION__,
			sprintf(
				/* translators: %s: FAIR Translations API URL. */
				__( 'Cannot establish a secure connection to %s. Please contact your server administrator.', 'fair'	),
				esc_url( $url )
			),
			headers_sent() || WP_DEBUG ? E_USER_WARNING : E_USER_NOTICE
		);

		$request = wp_remote_post( $http_url, $options );
	}

	if ( is_wp_error( $request ) ) {
		$result = new WP_Error(
			'translations_api_failed',
			sprintf(
				/* translators: %s: FAIR Translations API URL. */
				__( 'An unexpected error occurred while accessing the translations API at URL %s', 'fair' ),
				esc_url( $http_url )
			),
			$request->get_error_message()
		);
	} else {
		$result = json_decode( wp_remote_retrieve_body( $request ), true );
		if ( ! is_object( $result ) && ! is_array( $result ) ) {
			$result = new WP_Error(
				'translations_api_failed',
				sprintf(
					/* translators: %s: FAIR Translations API URL. */
					__( 'An unexpected error occurred while accessing the translations API at URL %s', 'fair' ),
					esc_url( $http_url )
				),
				wp_remote_retrieve_body( $request )
			);
		}
	}
	return $result;
}
