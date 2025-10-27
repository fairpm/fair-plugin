<?php
/**
 * Disable the Openverse media category in the block editor.
 *
 * @package FAIR
 */

namespace FAIR\Disable_Openverse;

/**
 * Bootstrap the module functionality.
 */
function bootstrap() {
	add_filter( 'block_editor_settings_all', __NAMESPACE__ . '\\disable_openverse_block_editor_settings' );
}

/**
 * Disable the Openverse media category in the block editor.
 *
 * @param array $settings The block editor settings.
 * @return array The modified block editor settings.
 */
function disable_openverse_block_editor_settings( array $settings ) : array {
	$settings['enableOpenverseMediaCategory'] = false;
	return $settings;
}
