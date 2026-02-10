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
const BROWSER_REGEX = '/Edge?\/14[34]\.0(\.\d+|)|Firefox\/(140\.0|1(4[6-9]|50)\.0)(\.\d+|)|Chrom(ium|e)\/(109\.0|1{2}2\.0|131\.0|13{2}\.0|139\.0|14[2-7]\.0)(\.\d+|)|(Maci|X1{2}).+ Version\/26\.[1-3]([,.]\d+|)( \(\w+\)|)( Mobile\/\w+|) Safari\/|Chrome.+OPR\/12[45]\.0\.\d+|(CPU[ +]OS|iPhone[ +]OS|CPU[ +]iPhone|CPU IPhone OS|CPU iPad OS)[ +]+(18[._][5-7]|26[._][1-3])([._]\d+|)|Opera Mini|Android:?[ /-]14{2}(\.0|)(\.\d+|)|Mobile Safari.+OPR\/8(0\.){2}\d+|Android.+Firefox\/147\.0(\.\d+|)|Android.+Chrom(ium|e)\/14{2}\.0(\.\d+|)|Android.+(UC? ?Browser|UCWEB|U3)[ /]?1(5\.){2}\d+|SamsungBrowser\/2[89]\.0|Android.+MQ{2}Browser\/14(\.9|)(\.\d+|)|K[Aa][Ii]OS\/(2\.5|3\.[01])(\.\d+|)/';

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
	$data = parse_user_agent( $agent );

	$default_data = [
		'platform'        => _x( 'your platform', 'operating system check', 'fair' ),
		'name'            => _x( 'your browser', 'browser version check', 'fair' ),
		'version'         => '',
		'current_version' => '',
		'upgrade'         => ! $supported,
		'insecure'        => ! $supported,
		'update_url'      => 'https://browsehappy.com/',
		'img_src'         => '',
		'img_src_ssl'     => '',
	];
	$data = array_merge( $default_data, $data );

	return [
		'response' => [
			'code' => 200,
			'message' => 'OK',
		],
		'body' => json_encode( $data ),
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
	$releases = get_site_transient( 'php_releases' );
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

	set_site_transient( 'php_releases', $indexed, CACHE_LIFETIME );
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

/**
 * Returns current version numbers for all browsers.
 *
 * These are for major release branches, not full build numbers.
 * Firefox 3.6, 4, etc., not Chrome 11.0.696.65.
 *
 * @return array Associative array of browser names with their respective
 *               current (or somewhat current) version number.
 */
function get_browser_current_versions() {
	return [
		'Chrome'            => '18', // Lowest version at the moment (mobile).
		'Firefox'           => '56',
		'Microsoft Edge'    => '15.15063',
		'Opera'             => '12.18',
		'Safari'            => '11',
		'Internet Explorer' => '11',
	];
}

/**
 * Returns browser data for a given browser.
 *
 * @param string|false $browser The name of the browser. Default false.
 * @return false|array|object {
 *     Array of data objects about browsers. False if the browser is unknown.
 *
 *     @type string    $name        Name of the browser.
 *     @type string    $url         The home URL for the browser.
 *     @type string    $img_src     The non-HTTPs URL for the browser's logo image.
 *     @type string    $img_src_ssl The HTTPS URL for the browser's logo image.
 * }
 */
function get_browser_data( $browser = false ) {

	$data = [
		'Internet Explorer' => (object) [
			'name'        => 'Internet Explorer',
			'url'         => 'https://support.microsoft.com/help/17621/internet-explorer-downloads',
		],
		'Edge' => (object) [
			'name'        => 'Microsoft Edge',
			'url'         => 'https://www.microsoft.com/edge',
		],
		'Firefox' => (object) [
			'name'        => 'Mozilla Firefox',
			'url'         => 'https://www.mozilla.org/firefox/',
		],
		'Safari' => (object) [
			'name'        => 'Safari',
			'url'         => 'https://www.apple.com/safari/',
		],
		'Opera' => (object) [
			'name'        => 'Opera',
			'url'         => 'https://www.opera.com/',
		],
		'Chrome' => (object) [
			'name'        => 'Google Chrome',
			'url'         => 'https://www.google.com/chrome',
		],
	];

	if ( false === $browser ) {
		return $data;
	}

	if ( ! isset( $data[ $browser ] ) ) {
		return false;
	}

	return $data[ $browser ];
}

/**
 * Returns an associative array of explicit browser token names and their
 * associated info.
 *
 * Explicit tokens are tokens that, if present, indicate a specific browser.
 *
 * If a browser is not identified by an explicit token, or s special
 * handling not supported by the default handler, then a new conditional block
 * for the browser instead needs to be added in parse_user_agent().
 *
 * In any case, the browser token name also needs to be added to the regex for
 * browser tokens in parse_user_agent().
 *
 * @return array {
 *     Associative array of browser tokens and their associated data.
 *
 *     @type array $data {
 *         Associative array of browser data. All are optional.
 *
 *         @type string $name        Name of browser, if it differs from the
 *                                   token name. Default is token name.
 *         @type bool   $use_version Should the 'Version' token, if present,
 *                                   supercede the version associated with the
 *                                   browser token? Default false.
 *         @type bool   $mobile      Does the browser signify the platform is
 *                                   mobile (for situations where it may no
 *                                   already be apparent)? Default false.
 *         @type string $platform    The name of the platform, to supercede
 *                                   whatever platform may have been detected.
 *                                   Default empty string.
 *     }
 * }
 */
function get_explicit_browser_tokens() {
	return [
		'Camino'         => [],
		'Chromium'       => [],
		'Edge'           => [
			'name' => 'Microsoft Edge',
		],
		'Kindle'         => [
			'name'        => 'Kindle Browser',
			'use_version' => true,
		],
		'Konqueror'      => [],
		'konqueror'      => [
			'name' => 'Konqueror',
		],
		'NokiaBrowser'   => [
			'name'   => 'Nokia Browser',
			'mobile' => true,
		],
		'Opera Mini'     => [ // Must be before 'Opera'.
			'mobile'      => true,
			'use_version' => true,
		],
		'Opera'          => [
			'use_version' => true,
		],
		'OPR'            => [
			'name'        => 'Opera',
			'use_version' => true,
		],
		'PaleMoon'       => [
			'name' => 'Pale Moon',
		],
		'QQBrowser'      => [
			'name' => 'QQ Browser',
		],
		'RockMelt'       => [],
		'SamsungBrowser' => [
			'name' => 'Samsung Browser',
		],
		'SeaMonkey'      => [],
		'Silk'           => [
			'name' => 'Amazon Silk',
		],
		'S40OviBrowser'  => [
			'name'     => 'Ovi Browser',
			'mobile'   => true,
			'platform' => 'Symbian',
		],
		'UCBrowser'      => [ // Must be before 'UCWEB'.
			'name' => 'UC Browser',
		],
		'UCWEB'          => [
			'name' => 'UC Browser',
		],
		'Vivaldi'        => [],
		'IEMobile'       => [ // Keep last just in case.
			'name' => 'Internet Explorer Mobile',
		],
	];
}

/**
 * Parses a user agent string into its important parts.
 *
 * @param string $user_agent The user agent string for a browser.
 * @return array {
 *     Array containing data based on the parsing of the user agent.
 *
 *     @type string $platform        The platform running the browser.
 *     @type string $name            The name of the browser.
 *     @type string $version         The reported version of the browser.
 *     @type string $update_url      The URL to obtain the update for the browser.
 *     @type string $img_src         The non-HTTPS URL for the browser's logo image.
 *     @type string $img_src_ssl     The HTTPS URL for the browser's logo image.
 *     @type string $current_version The current latest version of the browser.
 *     @type bool   $upgrade         Is there an update available for the browser?
 *     @type bool   $insecure        Is the browser insecure?
 *     @type bool   $mobile          Is the browser on a mobile platform?
 * }
 */
function parse_user_agent( $user_agent ) {
	$data = [
		'name'            => '',
		'version'         => '',
		'platform'        => '',
		'update_url'      => '',
		'img_src'         => '',
		'img_src_ssl'     => '',
		'current_version' => '',
		'upgrade'         => false,
		'insecure'        => false,
		'mobile'          => false,
	];
	$mobile_device = '';

	/**
	 * Identify platform/OS in user-agent string.
	 * '/(?P<platform>'                                      // Capture subpattern matches into 'platform' array.
	 * . 'Windows Phone( OS)?|Symbian|SymbOS|Android|iPhone' // Platform tokens.
	 * . '|iPad|Windows|Linux|Macintosh|FreeBSD|OpenBSD'     // More platform tokens.
	 * . '|SunOS|RIM Tablet OS|PlayBook'                     // More platform tokens.
	 * . ')'
	 * . '(?:'
	 * . ' (NT|amd64|armv7l|zvav)'                           // Possibly followed by specific modifiers/specifiers.
	 * . ')*'
	 * . '(?:'
	 * . ' [ix]?[0-9._]+'                                    // Possibly followed by architecture modifier (e.g. x86_64).
	 * . '(\-[0-9a-z\.\-]+)?'                                // Possibly followed by a hypenated version number.
	 * . ')*'
	 * . '(;|\))'                                            // Ending in a semi-colon or close parenthesis.
	 * . '/im',                                              // Case insensitive, multiline.
	 */
	if ( preg_match(
		'/(?P<platform>Windows Phone( OS)?|Symbian|SymbOS|Android|iPhone|iPad|Windows|Linux|Macintosh|FreeBSD|OpenBSD|SunOS|RIM Tablet OS|PlayBook)(?: (NT|amd64|armv7l|zvav))*(?: [ix]?[0-9._]+(\-[0-9a-z\.\-]+)?)*(;|\))/im',
		$user_agent,
		$regs
	) ) {
		$data['platform'] = $regs['platform'];
	}

	/**
	 * Find tokens of interest in user-agent string.
	 *
	 * '%(?P<name>'                                              // Capture subpattern matches into the 'name' array.
	 * .     'Opera Mini|Opera|OPR|Edge|UCBrowser|UCWEB'         // Browser tokens.
	 * .     '|QQBrowser|SymbianOS|Symbian|S40OviBrowser'        // More browser tokens.
	 * .     '|Trident|Silk|Konqueror|PaleMoon|Puffin'           // More browser tokens.
	 * .     '|SeaMonkey|Vivaldi|Camino|Chromium|Kindle|Firefox' // More browser tokens.
	 * .     '|SamsungBrowser|(?:Mobile )?Safari|NokiaBrowser'   // More browser tokens.
	 * .     '|MSIE|RockMelt|AppleWebKit|Chrome|IEMobile'        // More browser tokens.
	 * .     '|Version'                                          // Version token.
	 * . ')'
	 * . '(?:'
	 * .     '[/ ]'                                              // Forward slash or space.
	 * . ')'
	 * . '(?P<version>'                                          // Capture subpattern matches into 'version' array.
	 * .     '[0-9.]+'                                           // One or more numbers and/or decimal points.
	 * . ')'
	 * . '%im',                                                  // Case insensitive, multiline.
	 */
	preg_match_all(
		'%(?P<name>Opera Mini|Opera|OPR|Edge|UCBrowser|UCWEB|QQBrowser|SymbianOS|Symbian|S40OviBrowser|Trident|Silk|Konqueror|PaleMoon|Puffin|SeaMonkey|Vivaldi|Camino|Chromium|Kindle|Firefox|SamsungBrowser|(?:Mobile )?Safari|NokiaBrowser|MSIE|RockMelt|AppleWebKit|Chrome|IEMobile|Version)(?:[/ ])(?P<version>[0-9.]+)%im',
		$user_agent,
		$result,
		PREG_PATTERN_ORDER
	);

	// Create associative array with tokens as keys and versions as values.
	$tokens = array_combine( array_reverse( $result['name'] ), array_reverse( $result['version'] ) );

	// Properly set platform if Android is actually being reported.
	if ( 'Linux' === $data['platform'] && false !== strpos( $user_agent, 'Android' ) ) {
		if ( strpos( $user_agent, 'Kindle' ) ) {
			$data['platform'] = 'Fire OS';
		} else {
			$data['platform'] = 'Android';
		}
	} elseif ( 'Windows Phone' === $data['platform'] ) {
		// Normalize Windows Phone OS name when "OS" is omitted.
		$data['platform'] = 'Windows Phone OS';
	} elseif ( in_array( $data['platform'], [ 'Symbian', 'SymbOS' ] ) || ! empty( $tokens['SymbianOS'] ) || ! empty( $tokens['Symbian'] ) ) {
		// Standardize Symbian OS name.
		if ( ! in_array( $data['platform'], [ 'Symbian', 'SymbOS' ] ) ) {
			unset( $tokens['SymbianOS'] );
			unset( $tokens['Symbian'] );
		}
		$data['platform'] = 'Symbian';
	} elseif ( ! $data['platform'] && preg_match( '/BlackBerry|Nokia|SonyEricsson/', $user_agent, $matches ) ) {
		// Generically detect some mobile devices.
		$data['platform'] = 'Mobile';
		$mobile_device    = $matches[0];
	}

	// Flag known mobile platforms as mobile.
	if ( in_array( $data['platform'], [ 'Android', 'Fire OS', 'iPad', 'iPhone', 'Mobile', 'PlayBook', 'RIM Tablet OS', 'Symbian', 'Windows Phone OS' ] ) ) {
		$data['mobile'] = true;
	}

	// If Version/x.x.x was specified in UA string store it and ignore it.
	if ( ! empty( $tokens['Version'] ) ) {
		$version = $tokens['Version'];
		unset( $tokens['Version'] );
	}

	$explicit_tokens = get_explicit_browser_tokens();

	// No indentifiers provided.
	if ( ! $tokens ) {
		if ( 'BlackBerry' === $mobile_device ) {
			$data['name'] = 'BlackBerry Browser';
		} else {
			$data['name'] = 'unknown';
		}
	} elseif ( $found = array_intersect( array_keys( $explicit_tokens ), array_keys( $tokens ) ) ) { // phpcs:ignore Squiz.PHP.DisallowMultipleAssignments.FoundInControlStructure
		// Explicitly identified browser (info defined above in $explicit_tokens).
		$token = reset( $found );

		$data['name']    = $explicit_tokens[ $token ]['name'] ?? $token;
		$data['version'] = $tokens[ $token ];
		if ( empty( $explicit_tokens[ $token ]['use_version'] ) ) {
			$version = '';
		}
		if ( ! empty( $explicit_tokens[ $token ]['mobile'] ) ) {
			$data['mobile'] = true;
		}
		if ( ! empty( $explicit_tokens[ $token ]['platform'] ) ) {
			$data['platform'] = $explicit_tokens[ $token ]['platform'];
		}
	} elseif ( ! empty( $tokens['Puffin'] ) ) {
		// Puffin.
		$data['name']     = 'Puffin';
		$data['version']  = $tokens['Puffin'];
		$version          = '';
		// If not an already-identified mobile platform, set it as such.
		if ( ! $data['mobile'] ) {
			$data['mobile']   = true;
			$data['platform'] = '';
		}
	} elseif ( ! empty( $tokens['Trident'] ) ) {
		// Trident (Internet Explorer).
		// IE 8-10 more reliably report version via Trident token than MSIE token.
		// IE 11 uses Trident token without an MSIE token.
		// https://msdn.microsoft.com/library/hh869301(v=vs.85).aspx.
		$data['name'] = 'Internet Explorer';
		$trident_ie_mapping = [
			'4.0' => '8.0',
			'5.0' => '9.0',
			'6.0' => '10.0',
			'7.0' => '11.0',
		];
		$ver = $tokens['Trident'];
		$data['version'] = $trident_ie_mapping[ $ver ] ?? $ver;
	} elseif ( ! empty( $tokens['MSIE'] ) ) {
		// Internet Explorer (pre v8.0).
		$data['name'] = 'Internet Explorer';
		$data['version'] = $tokens['MSIE'];
	} elseif ( ! empty( $tokens['AppleWebKit'] ) ) {
		// AppleWebKit-emulating browsers.
		if ( ! empty( $tokens['Mobile Safari'] ) ) {
			if ( ! empty( $tokens['Chrome'] ) ) {
				$data['name'] = 'Chrome';
				$version = $tokens['Chrome'];
			} elseif ( 'Android' === $data['platform'] ) {
				$data['name'] = 'Android Browser';
			} elseif ( 'Fire OS' === $data['platform'] ) {
				$data['name'] = 'Kindle Browser';
			} elseif ( false !== strpos( $user_agent, 'BlackBerry' ) || false !== strpos( $user_agent, 'BB10' ) ) {
				$data['name']   = 'BlackBerry Browser';
				$data['mobile'] = true;

				if ( false !== stripos( $user_agent, 'BB10' ) ) {
					$tokens['Mobile Safari'] = '';
					$version = '';
				}
			} else {
				$data['name'] = 'Mobile Safari';
			}
		} elseif ( ! empty( $tokens['Chrome'] ) ) {
			$data['name'] = 'Chrome';
			$version = '';
		} elseif ( ! empty( $data['platform'] ) && 'PlayBook' == $data['platform'] ) {
			$data['name'] = 'PlayBook';
		} elseif ( ! empty( $tokens['Safari'] ) ) {
			if ( 'Android' === $data['platform'] ) {
				$data['name'] = 'Android Browser';
			} elseif ( 'Symbian' === $data['platform'] ) {
				$data['name'] = 'Nokia Browser';
				$tokens['Safari'] = '';
			} else {
				$data['name'] = 'Safari';
			}
		} else {
			$data['name'] = 'unknown';
			$tokens['AppleWebKit'] = '';
			$version = '';
		}
		$data['version'] = $tokens[ $data['name'] ] ?? '';
	} else {
		// Fall back to whatever is being reported.
		$ordered_tokens = array_reverse( $tokens );
		$data['version'] = reset( $ordered_tokens );
		$data['name'] = key( $ordered_tokens );
	}

	// Set the platform for Amazon-related browsers.
	if ( in_array( $data['name'], [ 'Amazon Silk', 'Kindle Browser' ] ) ) {
		$data['platform'] = 'Fire OS';
		$data['mobile']   = true;
	}

	// If Version/x.x.x was specified in UA string.
	if ( ! empty( $version ) ) {
		$data['version'] = $version;
	}

	if ( $data['mobile'] ) {
		// Generically set "Mobile" as the platform if a platform hasn't been set.
		if ( ! $data['platform'] ) {
			$data['platform'] = 'Mobile';
		}

		// Don't fetch additional browser data for mobile platform browsers at this time.
		return $data;
	}

	$browser_data            = get_browser_data( $data['name'] );
	$data['update_url']      = $browser_data ? $browser_data->url : '';
	$data['current_version'] = get_browser_version_from_name( $data['name'] );
	$data['upgrade']         = ( ! empty( $data['current_version'] ) && version_compare( $data['version'], $data['current_version'], '<' ) );

	if ( 'Internet Explorer' === $data['name'] ) {
		$data['insecure'] = true;
		$data['upgrade']  = true;
	} elseif ( 'Firefox' === $data['name'] && version_compare( $data['version'], '52', '<' ) ) {
		$data['insecure'] = true;
	} elseif ( 'Opera' === $data['name'] && version_compare( $data['version'], '12.18', '<' ) ) {
		$data['insecure'] = true;
	} elseif ( 'Safari' === $data['name'] && version_compare( $data['version'], '10', '<' ) ) {
		$data['insecure'] = true;
	}

	return $data;
}

/**
 * Returns the current version for the given browser.
 *
 * @param string $name The name of the browser.
 * @return string      The version for the browser or an empty string if an
 *                     unknown browser.
 */
function get_browser_version_from_name( $name ) {
	$versions = get_browser_current_versions();

	return isset( $versions[ $name ] ) ? $versions[ $name ] : '';
}
