<?php
/**
 * Replaces assets normally hosted on WordPress.org or the WordPress.com CDN with FAIR hosted copies.
 *
 * @package FAIR
 */

namespace FAIR\Assets;

const DEFAULT_EMOJI_BASE = 'https://cdn.jsdelivr.net/gh/jdecked/twemoji@15.1.0/assets/';

/**
 * Bootstrap.
 */
function bootstrap() {
    add_filter( 'emoji_url', __NAMESPACE__ . '\\replace_emoji_url' );
    add_filter( 'emoji_svg_url', __NAMESPACE__ . '\\replace_emoji_svg_url' );
}

/**
 * Get the base URL for the emoji images.
 *
 * @return string The base URL for emoji images. Must be in Twemoji format.
 */
function get_emoji_base_url() : string {
	if ( defined( 'FAIR_EMOJI_BASE_URL' ) ) {
		return FAIR_EMOJI_BASE_URL;
	}

	return DEFAULT_EMOJI_BASE;
}

/**
 * Replace the CDN domain for regular Twemoji images.
 *
 * @return string Replaced URL.
 */
function replace_emoji_url() {
    return get_emoji_base_url() . '72x72/';
}

/**
 * Replace the CDN domain for regular Twemoji images.
 *
 * @return string Replaced URL.
 */
function replace_emoji_svg_url() {
    return get_emoji_base_url() . 'svg/';
}
