<?php
/**
 * Plugin filtering functionality.
 *
 * @package FAIR
 */

namespace FAIR\Plugin_Filter;

use FAIR\Plugin_Filter\API_Client;

/**
 * Bootstrap the plugin filter module.
 *
 * @return void
 */
function bootstrap() {
	// Only run on admin screens.
	if ( ! is_admin() ) {
		return;
	}

	// Hook into plugins_api_result after AspireCloud returns results.
	add_filter( 'plugins_api_result', __NAMESPACE__ . '\filter_plugin_results', 12, 3 );
}

/**
 * Filter plugin results based on external API labels and risk scores.
 *
 * @param object|array|WP_Error $result Response object or WP_Error.
 * @param string                $action The type of information being requested from the Plugin Installation API.
 * @param object                $args   Plugin API arguments.
 * @return object|array|WP_Error Filtered response object or WP_Error.
 */
function filter_plugin_results( $result, string $action, object $args ) {
	// Check if filtering is enabled.
	if ( ! is_filtering_enabled() ) {
		return $result;
	}

	// Only filter query_plugins and plugin_information actions.
	if ( ! in_array( $action, [ 'query_plugins', 'plugin_information' ], true ) ) {
		return $result;
	}

	// Bail if result is an error or empty.
	if ( is_wp_error( $result ) || empty( $result ) ) {
		return $result;
	}

	// Handle single plugin information requests.
	if ( 'plugin_information' === $action ) {
		return filter_single_plugin( $result );
	}

	// Handle plugin list queries.
	if ( 'query_plugins' === $action && ! empty( $result->plugins ) ) {
		return filter_plugin_list( $result );
	}

	return $result;
}

/**
 * Filter a single plugin information result.
 *
 * @param object $result Plugin information object.
 * @return object|WP_Error Plugin information object or WP_Error if filtered.
 */
function filter_single_plugin( object $result ) {
	if ( empty( $result->slug ) ) {
		return $result;
	}

	$api_client = new API_Client();
	$labels = $api_client->get_plugin_labels( [ $result->slug ] );

	// Fail open if API is unavailable.
	if ( is_wp_error( $labels ) ) {
		return $result;
	}

	if ( should_filter_plugin( $result->slug, $labels ) ) {
		return new \WP_Error(
			'plugin_filtered',
			__( 'This plugin is not available for installation.', 'fair' ),
			[ 'status' => 404 ]
		);
	}

	return $result;
}

/**
 * Filter a list of plugins.
 *
 * @param object $result Plugin query result object with plugins array.
 * @return object Filtered plugin query result object.
 */
function filter_plugin_list( object $result ) {
	$slugs = array_map(
		function ( $plugin ) {
			return $plugin->slug ?? '';
		},
		$result->plugins
	);

	// Remove empty slugs.
	$slugs = array_filter( $slugs );

	if ( empty( $slugs ) ) {
		return $result;
	}

	$api_client = new API_Client();
	$labels = $api_client->get_plugin_labels( $slugs );

	// Fail open if API is unavailable.
	if ( is_wp_error( $labels ) ) {
		return $result;
	}

	// Filter out plugins that should be hidden.
	$result->plugins = array_values(
		array_filter(
			$result->plugins,
			function ( $plugin ) use ( $labels ) {
				$slug = $plugin->slug ?? '';
				return ! should_filter_plugin( $slug, $labels );
			}
		)
	);

	// Update info counts.
	$result->info['results'] = count( $result->plugins );

	return $result;
}

/**
 * Determine if a plugin should be filtered out.
 *
 * @param string $slug   Plugin slug.
 * @param array  $labels Labels data from external API.
 * @return bool True if plugin should be filtered, false otherwise.
 */
function should_filter_plugin( string $slug, array $labels ) : bool {
	if ( empty( $slug ) || empty( $labels[ $slug ] ) ) {
		return false;
	}

	$plugin_data = $labels[ $slug ];
	$settings = get_filter_settings();

	// Check block list.
	if ( ! empty( $plugin_data['blocked'] ) && $plugin_data['blocked'] === true ) {
		return true;
	}

	// Check risk score against threshold.
	if ( isset( $plugin_data['risk_score'] ) && isset( $settings['risk_threshold'] ) ) {
		if ( (float) $plugin_data['risk_score'] > (float) $settings['risk_threshold'] ) {
			return true;
		}
	}

	return false;
}

/**
 * Check if plugin filtering is enabled.
 *
 * @return bool True if enabled, false otherwise.
 */
function is_filtering_enabled() : bool {
	$settings = get_filter_settings();
	return ! empty( $settings['enabled'] );
}

/**
 * Get plugin filter settings.
 *
 * @return array Filter settings.
 */
function get_filter_settings() : array {
	$defaults = [
		'enabled' => false,
		'api_endpoint' => '',
		'risk_threshold' => 70,
		'cache_duration' => 12 * HOUR_IN_SECONDS,
		'block_list_mode' => 'strict',
	];

	$settings = get_option( 'fair_plugin_filter_settings', [] );

	return wp_parse_args( $settings, $defaults );
}

/**
 * Update plugin filter settings.
 *
 * @param array $settings Settings to save.
 * @return bool True if saved successfully, false otherwise.
 */
function update_filter_settings( array $settings ) : bool {
	$current = get_filter_settings();
	$updated = wp_parse_args( $settings, $current );

	return update_option( 'fair_plugin_filter_settings', $updated );
}
