<?php
/**
 * Configures FAIR hosted assets throughout WordPress.
 *
 * @package FAIR
 */

namespace FAIR\Assets;

const DEFAULT_EMOJI_BASE = 'https://cdn.jsdelivr.net/gh/jdecked/twemoji@17.0.2/assets/';

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
 * Configure the base URL for regular emoji images.
 *
 * @return string The base URL.
 */
function replace_emoji_url() {
	return get_emoji_base_url() . '72x72/';
}

/**
 * Configure the base URL for SVG emoji images.
 *
 * @return string The base URL.
 */
function replace_emoji_svg_url() {
	return get_emoji_base_url() . 'svg/';
}
