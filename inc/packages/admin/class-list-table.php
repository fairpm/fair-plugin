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
	 * Overrides parent views so we can use the filter bar display.
	 */
	public function views() {
		$views = $this->get_views();

		/** This filter is documented in wp-admin/includes/class-wp-list-table.php */
		$views = apply_filters( "views_{$this->screen->id}", $views );

		$this->screen->render_screen_reader_content( 'heading_views' );

		printf(
			'<p>' . __( 'Plugins extend and expand the functionality of WordPress. You may install plugins from the <a href="%s">FAIR Package Directory</a> right on this page, or upload a plugin in .zip format by clicking the button above.', 'fair' ) . '</p>',
			esc_url( 'https://fair.pm/packages/plugins/' )
		);
		?>
		<div class="wp-filter">
			<ul class="filter-links">
				<?php
				if ( ! empty( $views ) ) {
					foreach ( $views as $class => $view ) {
						$views[ $class ] = "\t<li class='$class'>$view";
					}
					echo wp_kses_post( implode( " </li>\n", $views ) ) . "</li>\n";
				}
				?>
			</ul>

			<?php install_search_form(); ?>
		</div>
		<?php
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
