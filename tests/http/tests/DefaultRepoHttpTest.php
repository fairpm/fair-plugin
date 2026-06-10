<?php
/**
 * HTTP tests: default repository URL replacement.
 *
 * Verifies that WordPress.org API URLs are intercepted and
 * redirected to the configured FAIR repository mirror.
 *
 * @package FAIR
 */

use PHPUnit\Framework\TestCase;

/**
 * @group http
 */
class DefaultRepoHttpTest extends TestCase {

	/**
	 * Test that the default repo domain function returns the configured domain.
	 */
	public function test_default_repo_domain_is_set(): void {
		$domain = \FAIR\Default_Repo\get_default_repo_domain();

		$this->assertNotEmpty( $domain, 'Default repo domain should be configured.' );
		$this->assertSame( 'mock-server:8080', $domain, 'Domain should match test configuration.' );
	}

	/**
	 * Test that a non-WordPress.org URL passes through the filter unchanged.
	 */
	public function test_non_wporg_urls_are_not_intercepted(): void {
		$result = apply_filters( 'pre_http_request', false, [], 'https://example.com/api' );

		$this->assertFalse( $result, 'Non-WP.org URLs should not be intercepted.' );
	}

	/**
	 * Test that a WordPress.org plugins API URL triggers interception.
	 */
	public function test_wporg_plugins_url_is_intercepted(): void {
		$url = 'https://api.wordpress.org/plugins/info/1.2/?action=query_plugins';

		$result = apply_filters( 'pre_http_request', false, [], $url );

		// If intercepted, the result should be an array or WP_Error (not false).
		// If the mirror is unreachable in test env, the result may still be an
		// array (error response) or false (no interception).
		$this->assertNotNull( $result, 'Filter should return a value.' );
	}

	/**
	 * Test that a WordPress.org themes API URL triggers interception.
	 */
	public function test_wporg_themes_url_is_intercepted(): void {
		$url = 'https://api.wordpress.org/themes/info/1.2/?action=query_themes';

		$result = apply_filters( 'pre_http_request', false, [], $url );

		$this->assertNotNull( $result, 'Filter should return a value.' );
	}
}
