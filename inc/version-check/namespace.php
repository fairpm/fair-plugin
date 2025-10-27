<?php
/**
 * Replaces calls to the WordPress.org BrowseHappy and ServeHappy APIs with static local data.
 *
 * @package FAIR
 */

namespace FAIR\Version_Check;

use const FAIR\CACHE_LIFETIME;

/**
 * This constant is replaced by bin/update-browsers.sh.
 *
 * DO NOT EDIT THIS CONSTANT MANUALLY.
 */
const BROWSER_REGEX = '/Edge?\/1(39|4[01])\.0(\.\d+|)|Firefox\/(140\.0|14[2-7]\.0)(\.\d+|)|Chrom(ium|e)\/(109\.0|1{2}2\.0|126\.0|134\.0|1(3[89]|4[0-4])\.0)(\.\d+|)|(Maci|X1{2}).+ Version\/(18\.[56]|26\.[01])([,.]\d+|)( \(\w+\)|)( Mobile\/\w+|) Safari\/|Chrome.+OPR\/12[12]\.0\.\d+|(CPU[ +]OS|iPhone[ +]OS|CPU[ +]iPhone|CPU IPhone OS|CPU iPad OS)[ +]+(18[._][56]|26[._][01])([._]\d+|)|Opera Mini|Android:?[ /-]141(\.0|)(\.\d+|)|Mobile Safari.+OPR\/8(0\.){2}\d+|Android.+Firefox\/143\.0(\.\d+|)|Android.+Chrom(ium|e)\/141\.0(\.\d+|)|Android.+(UC? ?Browser|UCWEB|U3)[ /]?1(5\.){2}\d+|SamsungBrowser\/2[78]\.0|Android.+MQ{2}Browser\/14(\.9|)(\.\d+|)|K[Aa][Ii]OS\/(2\.5|3\.[01])(\.\d+|)/';

/**
 * The latest branch of PHP which WordPress.org recommends.
 */
const RECOMMENDED_PHP = '7.4';

/**
 * The oldest branch of PHP which WordPress core still works with.
 */
const MINIMUM_PHP = '7.2.24';

/**
 * The lowest branch of PHP which is actively supported.
 *
 * (Fallback if we can't load PHP.net.)
 */
const SUPPORTED_PHP = '7.4';

/**
 * The lowest branch of PHP which is receiving security updates.
 *
 * (Fallback if we can't load PHP.net.)
 */
const SECURE_PHP = '7.4';

/**
 * The lowest branch of PHP which is still considered acceptable in WordPress.
 *
 * (Fallback if we can't load PHP.net.)
 */
const ACCEPTABLE_PHP = '7.4';

/**
 * Bootstrap.
 */
function bootstrap() {
	add_filter( 'pre_http_request', __NAMESPACE__ . '\\replace_browser_version_check', 10, 3 );
}

/**
 * Replace the browser version check.
 *
 * @param bool|array $value Filtered value, or false to proceed.
 * @param array $args HTTP request arguments.
 * @param string $url The request URL.
 * @return bool|array Replaced value, or false to proceed.
 */
function replace_browser_version_check( $value, $args, $url ) {
	if ( str_contains( $url, 'api.wordpress.org/core/browse-happy' ) ) {
		$agent = $args['body']['useragent'];
		return get_browser_check_response( $agent );
	}
	if ( str_contains( $url, 'api.wordpress.org/core/serve-happy' ) ) {
		$query = parse_url( $url, PHP_URL_QUERY );
		$url_args = wp_parse_args( $query );
		return get_server_check_response( $url_args['php_version'] ?? PHP_VERSION );
	}

	// Continue as we were.
	return $value;
}

/**
 * Check whether the agent matches, and return a fake response.
 *
 * @param string $agent User-agent to check.
 * @return array HTTP API response-like data.
 */
function get_browser_check_response( string $agent ) {
	// Switch delimiter to avoid conflicts.
	$regex = '#' . trim( BROWSER_REGEX, '/' ) . '#';
	$supported = preg_match( $regex, $agent, $matches );

	return [
		'response' => [
			'code' => 200,
			'message' => 'OK',
		],
		'body' => json_encode( [
			'platform' => _x( 'your platform', 'operating system check', 'fair' ),
			'name' => _x( 'your browser', 'browser version check', 'fair' ),
			'version' => '',
			'current_version' => '',
			'upgrade' => ! $supported,
			'insecure' => ! $supported,
			'update_url' => 'https://browsehappy.com/',
			'img_src' => '',
			'img_src_ssl' => '',
		] ),
		'headers' => [],
		'cookies' => [],
		'http_response_code' => 200,
	];
}

/**
 * Get PHP branch data from php.net
 *
 * @return array|null Branch-indexed data from PHP.net, or null on failure.
 */
function get_php_branches() {
	$releases = get_transient( 'php_releases' );
	if ( $releases ) {
		return $releases;
	}

	$response = wp_remote_get( 'https://www.php.net/releases/branches' );
	if ( is_wp_error( $response ) ) {
		// Failed - we'll fall back to hardcoded data.
		return null;
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $data ) ) {
		// Likely a server-level error - fall back to hardcoded data.
		return null;
	}

	// Index data by branch.
	$indexed = [];
	foreach ( $data as $ver ) {
		if ( empty( $ver['branch'] ) ) {
			continue;
		}

		$indexed[ $ver['branch'] ] = $ver;
	}

	set_transient( 'php_releases', $indexed, CACHE_LIFETIME );
	return $indexed;
}

/**
 * Check the PHP version against current versions.
 *
 * (WP sets is_lower_than_future_minimum manually based on >=7.4)
 *
 * The logic for the dashboard widget is:
 * - If is_acceptable, show nothing.
 * - Else if is_lower_than_future_minimum, show "PHP Update Required"
 * - Else, show "PHP Update Recommended"
 *
 * The logic for the Site Health check is:
 * - If the version is greater than recommended_version, show "running a recommended version"
 * - Else if is_supported, show "running on an older version"
 * - Else if is_secure and is_lower_than_future_minimum, show "outdated version which will soon not be supported"
 * - Else if is_secure, show "running on an older version which should be updated"
 * - Else if is_lower_than_future_minimum, show "outdated version which does not receive security updates and will soon not be supported"
 * - Else, show "outdated version which does not receive security updates"
 *
 * @param string $version Version to check.
 * @return array HTTP API response-like data.
 */
function check_php_version( string $version ) {
	$branches = get_php_branches();
	if ( empty( $branches ) ) {
		// Hardcoded fallback if we can't contact PHP.net.
		return [
			'recommended_version' => RECOMMENDED_PHP,
			'minimum_version'     => MINIMUM_PHP,
			'is_supported'        => version_compare( $version, SUPPORTED_PHP, '>=' ),
			'is_secure'           => version_compare( $version, SECURE_PHP, '>=' ),
			'is_acceptable'       => version_compare( $version, ACCEPTABLE_PHP, '>=' ),
		];
	}

	$min_stable = null;
	$min_secure = null;
	foreach ( $branches as $ver ) {
		// 'branch' is the major version.
		// 'latest' is the latest minor version on the branch.
		switch ( $ver['state'] ) {
			case 'stable':
				if ( $min_stable === null || version_compare( $ver['branch'], $min_stable, '<' ) ) {
					$min_stable = $ver['branch'];
					$min_secure = $ver['branch'];
				}
				break;

			case 'security':
				if ( $min_secure === null || version_compare( $ver['branch'], $min_secure, '<' ) ) {
					$min_secure = $ver['branch'];
				}
				break;

			case 'eol':
				// Ignore EOL versions.
				break;
		}
	}

	$ver_parts = explode( '.', $version );
	$cur_branch = sprintf( '%d.%d', $ver_parts[0], $ver_parts[1] );
	if ( empty( $branches[ $cur_branch ] ) ) {
		// Unknown version, likely future.
		return [
			'recommended_version' => $min_stable,
			'minimum_version'     => MINIMUM_PHP,
			'is_supported'        => version_compare( $version, $min_stable, '>=' ),
			'is_secure'           => version_compare( $version, $min_secure, '>=' ),
			'is_acceptable'       => version_compare( $version, $min_secure, '>=' ),
		];
	}

	$cur_branch_data = $branches[ $cur_branch ];

	if ( $cur_branch_data['state'] === 'security' ) {
		return [
			// If we're on the security branches, the recommended version
			// should be the latest version of this branch.
			'recommended_version' => $cur_branch_data['latest'],
			'minimum_version'     => MINIMUM_PHP,
			'is_supported'        => $cur_branch_data['state'] === 'stable',
			'is_secure'           => version_compare( $version, $cur_branch_data['latest'], '>=' ),
			'is_acceptable'       => version_compare( $version, $cur_branch_data['latest'], '>=' ),
		];
	}

	// Must be eol or future version.
	return [
		// Show the latest version of this branch or the minimum stable, whichever is greater.
		'recommended_version' => version_compare( $version, $min_stable, '>' ) ? $cur_branch_data['latest'] : $min_stable,
		'minimum_version'     => MINIMUM_PHP,
		'is_supported'        => version_compare( $version, $min_stable, '>=' ),
		'is_secure'           => version_compare( $version, $min_secure, '>=' ),
		'is_acceptable'       => version_compare( $version, $min_secure, '>=' ),
	];
}

/**
 * Get the server check shim response.
 *
 * @param string $version Version to check.
 * @return array HTTP API response-like data.
 */
function get_server_check_response( string $version ) {
	return [
		'response' => [
			'code' => 200,
			'message' => 'OK',
		],
		'body' => json_encode( check_php_version( $version ) ),
		'headers' => [],
		'cookies' => [],
		'http_response_code' => 200,
	];
}
