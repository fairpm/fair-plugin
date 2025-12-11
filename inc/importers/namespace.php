<?php
/**
 * Replaces calls to the WordPress.org Importer API with calls to the Importer API of the chosen FAIR repository.
 *
 * @package FAIR
 */

namespace FAIR\Importers;

/**
 * Bootstrap.
 */
function bootstrap() {
	add_filter( 'pre_http_request', __NAMESPACE__ . '\\replace_popular_importers_api', 10, 3 );
}

/**
 * Replace the retrieval of popular import plugins.
 *
 * @param false|array|WP_Error $response A preemptive return value of an HTTP request. Default false.
 * @param array $parsed_args HTTP request arguments.
 * @param string $url The request URL.
 * @return bool Replaced value, or false to proceed.
 */
function replace_popular_importers_api( $response, $parsed_args, $url ) {
	if ( str_contains( $url, 'api.wordpress.org/core/importers' ) ) {
		$query = parse_url( $url, PHP_URL_QUERY );
		parse_str( $query, $params );
		return get_popular_importers_response( $params['version'] );
	}

	// Continue as we were.
	return $response;
}

/**
 * Determine the version-specific list to request, and return a fake response.
 *
 * @param string $version The WordPress version string.
 * @return array HTTP API response-like data.
 */
function get_popular_importers_response( $version ) {
	$version = str_replace( '-src', '', $version );

	if ( version_compare( $version, '5.4-beta', '>=' ) ) {
		$popular_importers = get_popular_importers_gte_46();

		// Don't advertise the Blogroll importer.
		// See https://meta.trac.wordpress.org/ticket/4706.
		unset( $popular_importers['opml'] );
	} elseif ( version_compare( $version, '4.6-beta', '>=' ) ) {
		$popular_importers = get_popular_importers_gte_46();
	} else {
		$popular_importers = get_popular_importers_lt_46();
	}

	return [
		'response' => [
			'code' => 200,
			'message' => 'OK',
		],
		'body' => wp_json_encode( [
			'importers'  => $popular_importers,
			'translated' => false,
		] ),
		'headers' => [],
		'cookies' => [],
		'http_response_code' => 200,
	];
}

/**
 * Get the list of popular import plugins for 4.6-beta and later.
 *
 * This function is synced in wp-admin/includes/import.php of >= 4.6.
 *
 * Strings are translated by core.
 *
 * @return array The list of popular import plugins.
 */
function get_popular_importers_gte_46() {
	return [
		// slug => name, description, plugin slug, and register_importer() slug.
		'blogger' => [
			'name' => 'Blogger',
			'description' => 'Import posts, comments, and users from a Blogger blog.',
			'plugin-slug' => 'blogger-importer',
			'importer-id' => 'blogger',
		],
		'wpcat2tag' => [
			'name' => 'Categories and Tags Converter',
			'description' => 'Convert existing categories to tags or tags to categories, selectively.',
			'plugin-slug' => 'wpcat2tag-importer',
			'importer-id' => 'wp-cat2tag',
		],
		'livejournal' => [
			'name' => 'LiveJournal',
			'description' => 'Import posts from LiveJournal using their API.',
			'plugin-slug' => 'livejournal-importer',
			'importer-id' => 'livejournal',
		],
		'movabletype' => [
			'name' => 'Movable Type and TypePad',
			'description' => 'Import posts and comments from a Movable Type or TypePad blog.',
			'plugin-slug' => 'movabletype-importer',
			'importer-id' => 'mt',
		],
		'opml' => [
			'name' => 'Blogroll',
			'description' => 'Import links in OPML format.',
			'plugin-slug' => 'opml-importer',
			'importer-id' => 'opml',
		],
		'rss' => [
			'name' => 'RSS',
			'description' => 'Import posts from an RSS feed.',
			'plugin-slug' => 'rss-importer',
			'importer-id' => 'rss',
		],
		'tumblr' => [
			'name' => 'Tumblr',
			'description' => 'Import posts &amp; media from Tumblr using their API.',
			'plugin-slug' => 'tumblr-importer',
			'importer-id' => 'tumblr',
		],
		'wordpress' => [
			'name' => 'WordPress',
			'description' => 'Import posts, pages, comments, custom fields, categories, and tags from a WordPress export file.',
			'plugin-slug' => 'wordpress-importer',
			'importer-id' => 'wordpress',
		],
	];
}

/**
 * Get the list of popular import plugins for earlier than 4.5.
 *
 * This function is synced in wp-admin/includes/import.php of <= 4.5.
 *
 * Strings are translated by core.
 *
 * @return array The list of popular import plugins.
 */
function get_popular_importers_lt_46() {
	return [
		// slug => name, description, plugin slug, and register_importer() slug.
		'blogger' => [
			'name' => 'Blogger',
			'description' => 'Install the Blogger importer to import posts, comments, and users from a Blogger blog.',
			'plugin-slug' => 'blogger-importer',
			'importer-id' => 'blogger',
		],
		'wpcat2tag' => [
			'name' => 'Categories and Tags Converter',
			'description' => 'Install the category/tag converter to convert existing categories to tags or tags to categories, selectively.',
			'plugin-slug' => 'wpcat2tag-importer',
			'importer-id' => 'wpcat2tag',
		],
		'livejournal' => [
			'name' => 'LiveJournal',
			'description' => 'Install the LiveJournal importer to import posts from LiveJournal using their API.',
			'plugin-slug' => 'livejournal-importer',
			'importer-id' => 'livejournal',
		],
		'movabletype' => [
			'name' => 'Movable Type and TypePad',
			'description' => 'Install the Movable Type importer to import posts and comments from a Movable Type or TypePad blog.',
			'plugin-slug' => 'movabletype-importer',
			'importer-id' => 'mt',
		],
		'opml' => [
			'name' => 'Blogroll',
			'description' => 'Install the blogroll importer to import links in OPML format.',
			'plugin-slug' => 'opml-importer',
			'importer-id' => 'opml',
		],
		'rss' => [
			'name' => 'RSS',
			'description' => 'Install the RSS importer to import posts from an RSS feed.',
			'plugin-slug' => 'rss-importer',
			'importer-id' => 'rss',
		],
		'tumblr' => [
			'name' => 'Tumblr',
			'description' => 'Install the Tumblr importer to import posts &amp; media from Tumblr using their API.',
			'plugin-slug' => 'tumblr-importer',
			'importer-id' => 'tumblr',
		],
		'wordpress' => [
			'name' => 'WordPress',
			'description' => 'Install the WordPress importer to import posts, pages, comments, custom fields, categories, and tags from a WordPress export file.',
			'plugin-slug' => 'wordpress-importer',
			'importer-id' => 'wordpress',
		],
	];
}
