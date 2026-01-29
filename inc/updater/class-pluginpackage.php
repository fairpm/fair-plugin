<?php
/**
 * Plugin package data container.
 *
 * @package FAIR
 */

namespace FAIR\Updater;

/**
 * Represents a registered FAIR plugin.
 */
final class PluginPackage extends Package {

	/**
	 * Get the plugin slug.
	 *
	 * @return string The plugin directory name.
	 */
	public function get_slug(): string {
		return dirname( plugin_basename( $this->filepath ) );
	}

	/**
	 * Get the relative path used in update transients.
	 *
	 * @return string The relative path (e.g., 'my-plugin/my-plugin.php').
	 */
	public function get_relative_path(): string {
		return plugin_basename( $this->filepath );
	}
}
