<?php
/**
 * API client for external plugin filtering service.
 *
 * @package FAIR
 */

namespace FAIR\Plugin_Filter;

/**
 * API client for fetching plugin labels and risk scores.
 */
class API_Client {

	/**
	 * Cache key prefix.
	 *
	 * @var string
	 */
	const CACHE_PREFIX = 'fair_plugin_filter_';

	/**
	 * Maximum number of retry attempts.
	 *
	 * @var int
	 */
	const MAX_RETRIES = 2;

	/**
	 * API endpoint URL.
	 *
	 * @var string
	 */
	protected $api_endpoint;

	/**
	 * Cache duration in seconds.
	 *
	 * @var int
	 */
	protected $cache_duration;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$settings = \FAIR\Plugin_Filter\get_filter_settings();
		$this->api_endpoint = $settings['api_endpoint'] ?? '';
		$this->cache_duration = $settings['cache_duration'] ?? 12 * HOUR_IN_SECONDS;
	}

	/**
	 * Get plugin labels for multiple plugins.
	 *
	 * @param array $slugs Array of plugin slugs.
	 * @return array|\WP_Error Array of plugin data keyed by slug, or WP_Error on failure.
	 */
	public function get_plugin_labels( array $slugs ) {
		if ( empty( $slugs ) ) {
			return [];
		}

		// Remove empty slugs.
		$slugs = array_filter( $slugs );

		if ( empty( $slugs ) ) {
			return [];
		}

		// Check if API endpoint is configured.
		if ( empty( $this->api_endpoint ) ) {
			return new \WP_Error(
				'api_not_configured',
				__( 'Plugin filter API endpoint is not configured.', 'fair' )
			);
		}

		// Try to get from cache.
		$cached_data = $this->get_cached_labels( $slugs );
		$uncached_slugs = array_diff( $slugs, array_keys( $cached_data ) );

		// If all data is cached, return it.
		if ( empty( $uncached_slugs ) ) {
			return $cached_data;
		}

		// Fetch uncached slugs from API.
		$fresh_data = $this->fetch_from_api( $uncached_slugs );

		// If API call failed, return cached data (fail open).
		if ( is_wp_error( $fresh_data ) ) {
			// If we have some cached data, use it.
			if ( ! empty( $cached_data ) ) {
				return $cached_data;
			}
			return $fresh_data;
		}

		// Cache the fresh data.
		$this->cache_labels( $fresh_data );

		// Merge cached and fresh data.
		return array_merge( $cached_data, $fresh_data );
	}

	/**
	 * Fetch plugin labels from the external API.
	 *
	 * @param array $slugs Array of plugin slugs.
	 * @return array|\WP_Error Array of plugin data or WP_Error on failure.
	 */
	protected function fetch_from_api( array $slugs ) {
		$url = add_query_arg(
			[
				'slugs' => implode( ',', $slugs ),
			],
			$this->api_endpoint
		);

		$response = wp_remote_get(
			$url,
			[
				'timeout' => 10,
				'headers' => [
					'Accept' => 'application/json',
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'api_request_failed',
				sprintf(
					/* translators: %s: Error message */
					__( 'Failed to fetch plugin labels: %s', 'fair' ),
					$response->get_error_message()
				)
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code !== 200 ) {
			return new \WP_Error(
				'api_error_response',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'Plugin filter API returned error status: %d', 'fair' ),
					$status_code
				)
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new \WP_Error(
				'api_invalid_json',
				__( 'Plugin filter API returned invalid JSON.', 'fair' )
			);
		}

		// Validate response structure.
		if ( ! is_array( $data ) ) {
			return new \WP_Error(
				'api_invalid_response',
				__( 'Plugin filter API returned invalid response format.', 'fair' )
			);
		}

		return $data;
	}

	/**
	 * Get cached labels for plugins.
	 *
	 * @param array $slugs Array of plugin slugs.
	 * @return array Array of cached plugin data keyed by slug.
	 */
	protected function get_cached_labels( array $slugs ) : array {
		$cached = [];

		foreach ( $slugs as $slug ) {
			$cache_key = $this->get_cache_key( $slug );
			$cached_data = get_transient( $cache_key );

			if ( false !== $cached_data ) {
				$cached[ $slug ] = $cached_data;
			}
		}

		return $cached;
	}

	/**
	 * Cache plugin labels.
	 *
	 * @param array $labels Array of plugin data keyed by slug.
	 * @return void
	 */
	protected function cache_labels( array $labels ) : void {
		foreach ( $labels as $slug => $data ) {
			$cache_key = $this->get_cache_key( $slug );
			set_transient( $cache_key, $data, $this->cache_duration );
		}
	}

	/**
	 * Get cache key for a plugin slug.
	 *
	 * @param string $slug Plugin slug.
	 * @return string Cache key.
	 */
	protected function get_cache_key( string $slug ) : string {
		return self::CACHE_PREFIX . md5( $slug );
	}

	/**
	 * Clear all cached labels.
	 *
	 * @return void
	 */
	public function clear_cache() : void {
		global $wpdb;

		// Delete all transients with our prefix.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_' . self::CACHE_PREFIX ) . '%'
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_timeout_' . self::CACHE_PREFIX ) . '%'
			)
		);
	}

	/**
	 * Test API connection.
	 *
	 * @return bool|\WP_Error True if connection successful, WP_Error otherwise.
	 */
	public function test_connection() {
		if ( empty( $this->api_endpoint ) ) {
			return new \WP_Error(
				'api_not_configured',
				__( 'Plugin filter API endpoint is not configured.', 'fair' )
			);
		}

		// Test with a dummy plugin slug.
		$result = $this->fetch_from_api( [ 'test-plugin' ] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}
}
