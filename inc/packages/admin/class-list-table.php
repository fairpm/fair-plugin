<?php
/**
 * Custom list table.
 *
 * @package FAIR
 */

namespace FAIR\Packages\Admin;

use FAIR\Packages;
use WP_Plugin_Install_List_Table;

/**
 * Custom plugin installer list table.
 */
class List_Table extends WP_Plugin_Install_List_Table {

	/**
	 * Replace Add Plugins message with ours.
	 *
	 * @since WordPress 6.9.0
	 * @return void
	 */
	public function views() {
		ob_start();
		parent::views();
		$views = ob_get_clean();

		add_filter( 'wp_kses_allowed_html', [ $this, 'my_allow_forms_in_kses' ], 10, 2 );
		$allowed_html = wp_kses_allowed_html( 'post' );
		remove_filter( 'wp_kses_allowed_html', [ $this, 'my_allow_forms_in_kses' ] );

		echo wp_kses(
			str_replace(
    			// phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- Intentional use of Core's text domain.
				[ __( 'https://wordpress.org/plugins/' ), __( 'WordPress Plugin Directory' ) ],
				[ esc_url( 'https://fair.pm/packages/plugins/' ), __( 'FAIR Package Directory', 'fair' ) ],
				$views
			), $allowed_html
		);
	}

	/**
	 * Add search form tags for wp_kses.
	 *
	 * @param  array  $tags    Array of allowed HTML tags.
	 * @param  string $context Context of the allowed HTML.
	 * @return array
	 */
	public function my_allow_forms_in_kses( $tags, $context ) {
		if ( 'post' === $context ) {
			$tags['form'] = [
				'action' => true,
				'method' => true,
				'name'   => true,
				'class'  => true,
			];
			$tags['input'] = [
				'type' => true,
				'name' => true,
				'value' => true,
				'id'   => true,
				'class' => true,
			];
			$tags['label']  = [
				'for' => true,
			];
			$tags['select'] = [
				'name' => true,
				'id'   => true,
			];
			$tags['option'] = [
				'value' => true,
				'selected' => true,
			];
		}

		return $tags;
	}

	/**
	 * Generates the list table rows.
	 *
	 * @since 3.1.0
	 */
	public function display_rows() {
		ob_start();
		parent::display_rows();
		$res = ob_get_clean();

		// Find all DID slug classes, and add the *other* slug class.
		$res = preg_replace_callback( '/class="plugin-card plugin-card-([^ ]+)-(did--[^ ]+)"/', function ( $matches ) {
			$slug = $matches[1];
			$did = str_replace( '--', ':', $matches[2] );
			$hash = Packages\get_did_hash( $did );
			return sprintf(
				'class="plugin-card plugin-card-%1$s-%2$s plugin-card-%1$s-%3$s"',
				$slug,
				$matches[2],
				$hash
			);
		}, $res );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Raw HTML.
		echo $res;
	}
}
