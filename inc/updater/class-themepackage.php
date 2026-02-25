<?php
/**
 * Theme package data container.
 *
 * @package FAIR
 */

namespace FAIR\Updater;

/**
 * Represents a registered FAIR theme.
 */
final class ThemePackage extends Package {

	/**
	 * Get the theme slug.
	 *
	 * @return string The theme stylesheet (directory name).
	 */
	public function get_slug(): string {
		return basename( dirname( $this->filepath ) );
	}

	/**
	 * Get the relative path used in update transients.
	 *
	 * @return string The relative path (theme directory name).
	 */
	public function get_relative_path(): string {
		return dirname( plugin_basename( $this->filepath ) );
	}
}
