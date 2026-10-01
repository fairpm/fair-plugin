<?php
/**
 * Implements the plugin settings page.
 *
 * @package FAIR
 */

namespace FAIR\Site_Health;

use const FAIR\CACHE_LIFETIME;
use const FAIR\CACHE_LIFETIME_FAILURE;
use const FAIR\VERSION;
use WP_Error;

const PLUGIN_API_RESPONSE_SIZE_WARNING = 1024 * 1024;
const PLUGIN_API_RESPONSE_TIME_WARNING = 10.0;

/**
 * Bootstrap.
 */
function bootstrap() {
	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_media_scripts' );
	add_filter( 'debug_information', __NAMESPACE__ . '\\filter_debug_information' );
	add_filter( 'site_status_tests', __NAMESPACE__ . '\\register_tests' );
	add_filter( 'pre_http_request', __NAMESPACE__ . '\\replace_wordpress_org_health_check', 10, 3 );
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

	wp_enqueue_script( 'fair-site-health', esc_url( plugin_dir_url( \FAIR\PLUGIN_FILE ) . 'assets/js/fair-site-health.js' ), [ 'wp-i18n' ], VERSION, true );
	wp_localize_script(
		'fair-site-health',
		'fairSiteHealth',
		[
			'defaultRepoDomain' => \FAIR\Default_Repo\get_default_repo_domain(),
			'repoIPAddress' => gethostbyname( \FAIR\Default_Repo\get_default_repo_domain() ),
			'errorMessageRegex' => build_error_message_regex(),
		]
	);
}

/**
 * Set up the regular expression used for handling the Core test error message.
 *
 * @return string
 */
function build_error_message_regex(): string {
	$regex = str_replace(
		[ '%1\\$s', '%2\\$s' ],
		[ '(?:.*?)', '(.*)' ],
		preg_quote( __( 'Your site is unable to reach WordPress.org at %1$s, and returned the error: %2$s' ) ) // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
	);

	return $regex . '<\\/p>';
}

/**
 * Replace the bare WordPress.org request made by Core Site Health.
 *
 * Other WordPress.org API replacements remain owned by their existing modules.
 * This exact root URL is used only by Core's dotorg_communication test.
 *
 * @param false|array|WP_Error $response Filtered response.
 * @param array                $args HTTP request arguments.
 * @param string               $url HTTP request URL.
 * @return false|array|WP_Error
 */
function replace_wordpress_org_health_check( $response, array $args, string $url ) {
	static $is_replacing = false;
	if ( $is_replacing || false !== $response ) {
		return $response;
	}

	$parts = wp_parse_url( $url );
	if (
		'api.wordpress.org' !== ( $parts['host'] ?? '' )
		|| ! in_array( $parts['path'] ?? '', [ '', '/' ], true )
		|| isset( $parts['query'] )
	) {
		return $response;
	}

	$repo_domain = \FAIR\Default_Repo\get_default_repo_domain();
	if ( 'api.wordpress.org' === $repo_domain ) {
		return $response;
	}

	$target_url = add_query_arg( '_fair', VERSION, 'https://' . $repo_domain );
	$is_replacing = true;
	$response = wp_remote_request( $target_url, $args );
	$is_replacing = false;

	return $response;
}

/**
 * Register FAIR Site Health tests.
 *
 * @param array $tests Site Health tests.
 * @return array
 */
function register_tests( array $tests ): array {
	$tests['direct']['fair_plugin_directory_api'] = [
		'label' => __( 'FAIR plugin directory API', 'fair' ),
		'test' => __NAMESPACE__ . '\\test_plugin_directory_api',
		'skip_cron' => true,
	];

	return $tests;
}

/**
 * Make the server-side plugin directory request used by Add Plugins.
 *
 * The request starts with the WordPress.org URL so it exercises the same
 * pre_http_request replacement as plugins_api(). The request payload matches
 * WP_Plugin_Install_List_Table::prepare_items() for the Featured tab.
 *
 * @return array
 */
function get_plugin_directory_api_diagnostics(): array {
	$request_url = get_plugin_directory_request_url();
	$repo_domain = \FAIR\Default_Repo\get_default_repo_domain();
	$start = microtime( true );
	$response = wp_remote_get(
		$request_url,
		[
			'timeout' => 15,
			'user-agent' => 'WordPress/' . wp_get_wp_version() . '; ' . home_url( '/' ),
		]
	);

	return [
		'repository' => $repo_domain,
		'repository_ips' => get_repository_ip_addresses( $repo_domain ),
		'request_payload' => get_plugin_directory_request_summary(),
		'elapsed' => microtime( true ) - $start,
		'response' => $response,
	];
}

/**
 * Test the server-side plugin directory request used by Add Plugins.
 *
 * @return array
 */
function test_plugin_directory_api(): array {
	$diagnostics = get_plugin_directory_api_diagnostics();
	$response = $diagnostics['response'];
	$elapsed = $diagnostics['elapsed'];

	$details = [
		__( 'Repository', 'fair' ) => $diagnostics['repository'],
		__( 'Resolved IP addresses', 'fair' ) => implode( ', ', $diagnostics['repository_ips'] ),
		__( 'Request payload', 'fair' ) => $diagnostics['request_payload'],
		__( 'Elapsed time', 'fair' ) => number_format_i18n( $elapsed, 3 ) . ' s',
	];

	if ( is_wp_error( $response ) ) {
		$details[ __( 'WordPress error code', 'fair' ) ] = $response->get_error_code();
		$details[ __( 'WordPress error message', 'fair' ) ] = $response->get_error_message();
		$details[ __( 'WordPress error data', 'fair' ) ] = format_error_data( $response );

		return build_test_result(
			'critical',
			__( 'The FAIR plugin directory request failed', 'fair' ),
			__( 'The server could not complete the same HTTPS request used by the Add Plugins screen.', 'fair' ),
			$details
		);
	}

	$status_code = wp_remote_retrieve_response_code( $response );
	$body = wp_remote_retrieve_body( $response );
	$headers = wp_remote_retrieve_headers( $response );
	$data = json_decode( $body, true );
	$body_size = strlen( $body );

	$details[ __( 'HTTP status', 'fair' ) ] = (string) $status_code;
	$details[ __( 'Decoded response size', 'fair' ) ] = size_format( $body_size, 2 ) . ' (' . $body_size . ' bytes)';
	$details[ __( 'Content-Type', 'fair' ) ] = (string) ( $headers['content-type'] ?? '' );
	$details[ __( 'Cache-Control', 'fair' ) ] = (string) ( $headers['cache-control'] ?? __( 'Not provided', 'fair' ) );
	$details[ __( 'Response date', 'fair' ) ] = (string) ( $headers['date'] ?? __( 'Not provided', 'fair' ) );
	$details[ __( 'Plugins returned', 'fair' ) ] = is_array( $data['plugins'] ?? null ) ? (string) count( $data['plugins'] ) : '0';

	if ( 200 !== $status_code ) {
		return build_test_result(
			'critical',
			__( 'The FAIR plugin directory returned an HTTP error', 'fair' ),
			__( 'The Add Plugins screen requires a successful HTTP 200 response.', 'fair' ),
			$details
		);
	}

	if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) || ! isset( $data['plugins'] ) || ! isset( $data['info'] ) ) {
		$details[ __( 'JSON error', 'fair' ) ] = json_last_error_msg();
		return build_test_result(
			'critical',
			__( 'The FAIR plugin directory returned an invalid response', 'fair' ),
			__( 'The response was not a valid WordPress plugin directory listing.', 'fair' ),
			$details
		);
	}

	if ( $body_size > PLUGIN_API_RESPONSE_SIZE_WARNING || $elapsed >= PLUGIN_API_RESPONSE_TIME_WARNING ) {
		return build_test_result(
			'recommended',
			__( 'The FAIR plugin directory works, but its response is large or slow', 'fair' ),
			__( 'Large or slow responses can exceed the 15-second timeout used by Add Plugins on some servers.', 'fair' ),
			$details
		);
	}

	return build_test_result(
		'good',
		__( 'The FAIR plugin directory is available', 'fair' ),
		__( 'The server successfully completed and decoded the plugin listing request used by Add Plugins.', 'fair' ),
		$details
	);
}

/**
 * Get the plugin directory request URL generated by plugins_api().
 *
 * @return string
 */
function get_plugin_directory_request_url(): string {
	return add_query_arg(
		[
			'action' => 'query_plugins',
			'request' => get_plugin_directory_request_parameters(),
		],
		'https://api.wordpress.org/plugins/info/1.2/'
	);
}

/**
 * Get the plugin listing request parameters used by Add Plugins.
 *
 * @return array
 */
function get_plugin_directory_request_parameters(): array {
	return [
		'page' => 1,
		'per_page' => 36,
		'locale' => get_user_locale(),
		'browse' => 'featured',
		'wp_version' => substr( wp_get_wp_version(), 0, 3 ),
	];
}

/**
 * Get a readable representation of the plugin listing request payload.
 *
 * Spaces after separators allow long payloads to wrap in Site Health tables.
 *
 * @return string
 */
function get_plugin_directory_request_summary(): string {
	$parameters = get_plugin_directory_request_parameters();
	$summary = sprintf(
		'action=query_plugins; page=%d; per_page=%d; locale=%s; browse=%s; wp_version=%s',
		$parameters['page'],
		$parameters['per_page'],
		$parameters['locale'],
		$parameters['browse'],
		$parameters['wp_version']
	);

	if ( 'api.wordpress.org' !== \FAIR\Default_Repo\get_default_repo_domain() ) {
		$summary .= '; _fair=' . VERSION;
	}

	return $summary;
}

/**
 * Resolve all available IP addresses for the repository.
 *
 * @param string $domain Repository domain.
 * @return string[]
 */
function get_repository_ip_addresses( string $domain ): array {
	$addresses = gethostbynamel( $domain );
	return is_array( $addresses ) && $addresses ? $addresses : [ __( 'DNS lookup failed', 'fair' ) ];
}

/**
 * Build a Site Health result.
 *
 * @param string $status Status: good, recommended, or critical.
 * @param string $label Result label.
 * @param string $summary Result summary.
 * @param array  $details Request details.
 * @return array
 */
function build_test_result( string $status, string $label, string $summary, array $details ): array {
	$description = '<p>' . esc_html( $summary ) . '</p><ul>';
	foreach ( $details as $detail_label => $value ) {
		$description .= sprintf(
			'<li><strong>%s:</strong> <code>%s</code></li>',
			esc_html( (string) $detail_label ),
			esc_html( (string) $value )
		);
	}
	$description .= '</ul>';

	return [
		'label' => $label,
		'status' => $status,
		'badge' => [
			'label' => __( 'FAIR Connect', 'fair' ),
			'color' => 'blue',
		],
		'description' => $description,
		'actions' => '',
		'test' => 'fair_plugin_directory_api',
	];
}

/**
 * Format the diagnostic data attached to a WordPress HTTP error.
 *
 * @param WP_Error $error WordPress error.
 * @return string
 */
function format_error_data( WP_Error $error ): string {
	$data = $error->get_error_data();
	if ( null === $data || '' === $data ) {
		return __( 'None', 'fair' );
	}
	if ( is_scalar( $data ) ) {
		return (string) $data;
	}
	return (string) wp_json_encode( $data );
}

/**
 * Build a Site Health debug field.
 *
 * @param string $label Field label.
 * @param string $value Field value.
 * @return array
 */
function build_debug_field( string $label, string $value ): array {
	return [
		'label' => $label,
		'value' => $value,
		'debug' => $value,
	];
}

/**
 * Get copyable server-side plugin API diagnostics for Site Health Info.
 *
 * @return array
 */
function get_plugin_directory_debug_fields(): array {
	$diagnostics = get_plugin_directory_api_diagnostics();
	$response = $diagnostics['response'];
	$elapsed = $diagnostics['elapsed'];
	$fields = [
		'plugin_api_availability' => build_debug_field( __( 'Plugin listing API availability', 'fair' ), is_wp_error( $response ) ? 'unavailable' : 'available' ),
		'plugin_api_response_time' => build_debug_field( __( 'Plugin listing API response time', 'fair' ), number_format_i18n( $elapsed, 3 ) . ' seconds' ),
	];

	if ( is_wp_error( $response ) ) {
		$fields['plugin_api_error_code'] = build_debug_field( __( 'Plugin listing API error code', 'fair' ), $response->get_error_code() );
		$fields['plugin_api_error_message'] = build_debug_field( __( 'Plugin listing API error message', 'fair' ), $response->get_error_message() );
		$fields['plugin_api_error_data'] = build_debug_field( __( 'Plugin listing API error data', 'fair' ), format_error_data( $response ) );
		return $fields;
	}

	$status_code = wp_remote_retrieve_response_code( $response );
	$body = wp_remote_retrieve_body( $response );
	$headers = wp_remote_retrieve_headers( $response );
	$data = json_decode( $body, true );
	$body_size = strlen( $body );
	$is_valid = 200 === $status_code && JSON_ERROR_NONE === json_last_error() && is_array( $data ) && isset( $data['plugins'] ) && isset( $data['info'] );
	$is_large_or_slow = $body_size > PLUGIN_API_RESPONSE_SIZE_WARNING || $elapsed >= PLUGIN_API_RESPONSE_TIME_WARNING;

	if ( ! $is_valid ) {
		$fields['plugin_api_availability'] = build_debug_field( __( 'Plugin listing API availability', 'fair' ), 'invalid response' );
	} elseif ( $is_large_or_slow ) {
		$fields['plugin_api_availability'] = build_debug_field( __( 'Plugin listing API availability', 'fair' ), 'available; response is large or slow' );
	}

	$fields['plugin_api_http_status'] = build_debug_field( __( 'Plugin listing API HTTP status', 'fair' ), (string) $status_code );
	$fields['plugin_api_response_size'] = build_debug_field( __( 'Plugin listing API decoded response size', 'fair' ), $body_size . ' bytes' );
	$fields['plugin_api_plugins_returned'] = build_debug_field( __( 'Plugin listing API plugins returned', 'fair' ), is_array( $data['plugins'] ?? null ) ? (string) count( $data['plugins'] ) : '0' );
	$fields['plugin_api_content_type'] = build_debug_field( __( 'Plugin listing API Content-Type', 'fair' ), (string) ( $headers['content-type'] ?? '' ) );
	$fields['plugin_api_cache_control'] = build_debug_field( __( 'Plugin listing API Cache-Control', 'fair' ), (string) ( $headers['cache-control'] ?? 'not provided' ) );
	$fields['plugin_api_response_date'] = build_debug_field( __( 'Plugin listing API response date', 'fair' ), (string) ( $headers['date'] ?? 'not provided' ) );
	if ( JSON_ERROR_NONE !== json_last_error() ) {
		$fields['plugin_api_json_error'] = build_debug_field( __( 'Plugin listing API JSON error', 'fair' ), json_last_error_msg() );
	}

	return $fields;
}

/**
 * Filter debug information.
 *
 * @param array $info {
 *     The debug information to be added to the core information page.
 *
 *     This is an associative multi-dimensional array, up to three levels deep.
 *     The topmost array holds the sections, keyed by section ID.
 *
 *     @type array ...$0 {
 *         Each section has a `$fields` associative array (see below), and each `$value` in `$fields`
 *         can be another associative array of name/value pairs when there is more structured data
 *         to display.
 *
 *         @type string $label       Required. The title for this section of the debug output.
 *         @type string $description Optional. A description for your information section which
 *                                   may contain basic HTML markup, inline tags only as it is
 *                                   outputted in a paragraph.
 *         @type bool   $show_count  Optional. If set to `true`, the amount of fields will be included
 *                                   in the title for this section. Default false.
 *         @type bool   $private     Optional. If set to `true`, the section and all associated fields
 *                                   will be excluded from the copied data. Default false.
 *         @type array  $fields {
 *             Required. An associative array containing the fields to be displayed in the section,
 *             keyed by field ID.
 *
 *             @type array ...$0 {
 *                 An associative array containing the data to be displayed for the field.
 *
 *                 @type string $label    Required. The label for this piece of information.
 *                 @type mixed  $value    Required. The output that is displayed for this field.
 *                                        Text should be translated. Can be an associative array
 *                                        that is displayed as name/value pairs.
 *                                        Accepted types: `string|int|float|(string|int|float)[]`.
 *                 @type string $debug    Optional. The output that is used for this field when
 *                                        the user copies the data. It should be more concise and
 *                                        not translated. If not set, the content of `$value`
 *                                        is used. Note that the array keys are used as labels
 *                                        for the copied data.
 *                 @type bool   $private  Optional. If set to `true`, the field will be excluded
 *                                        from the copied data, allowing you to show, for example,
 *                                        API keys here. Default false.
 *             }
 *         }
 *     }
 * }
 */
function filter_debug_information( $info ) {
	$repo_domain = \FAIR\Default_Repo\get_default_repo_domain();

	// The Core test now reaches the configured repository through the Site Health replacement.
	$info['wp-core']['fields']['dotorg_communication']['label'] = sprintf( __( 'Communication with %s', 'fair' ), $repo_domain );
	$info['wp-core']['fields']['dotorg_communication']['value'] = sprintf( __( '%s is reachable', 'fair' ), $repo_domain );

	$fields = [
		'version' => build_debug_field( __( 'Version', 'fair' ), VERSION ),
		'repository' => build_debug_field( __( 'Default repository', 'fair' ), $repo_domain ),
		'repository_ips' => build_debug_field( __( 'Repository IP addresses', 'fair' ), implode( ', ', get_repository_ip_addresses( $repo_domain ) ) ),
		'plugin_api_payload' => build_debug_field( __( 'Plugin listing API request payload', 'fair' ), get_plugin_directory_request_summary() ),
		'plc_directory' => build_debug_field( __( 'PLC directory', 'fair' ), defined( 'FAIR_PLC_DIRECTORY_URL' ) ? (string) \FAIR_PLC_DIRECTORY_URL : 'https://plc.directory' ),
		'metadata_cache_lifetime' => build_debug_field( __( 'Metadata cache lifetime', 'fair' ), (string) CACHE_LIFETIME . ' seconds' ),
		'failure_cache_lifetime' => build_debug_field( __( 'Failure cache lifetime', 'fair' ), (string) CACHE_LIFETIME_FAILURE . ' seconds' ),
		'external_object_cache' => build_debug_field( __( 'External object cache', 'fair' ), wp_using_ext_object_cache() ? 'true' : 'false' ),
	];

	$info['fair-connect'] = [
		'label' => __( 'FAIR Connect', 'fair' ),
		'description' => __( 'Configuration and live server-side diagnostics used for FAIR package discovery and plugin listings.', 'fair' ),
		'show_count' => true,
		'fields' => array_merge( $fields, get_plugin_directory_debug_fields() ),
	];

	return $info;
}
