<?php

namespace FAIR\Packages\Admin;

use FAIR;
use FAIR\Packages;

const TAB_DIRECT = 'fair_direct';

function bootstrap() {
	if ( ! is_admin() ) {
		return;
	}

	add_filter( 'install_plugins_tabs', __NAMESPACE__ . '\\add_direct_tab' );
	add_action( 'install_plugins_' . TAB_DIRECT, __NAMESPACE__ . '\\render_tab_direct' );
	add_action( 'load-plugin-install.php', __NAMESPACE__. '\\load_plugin_install' );
	add_action( 'install_plugins_pre_plugin-information', __NAMESPACE__ . '\\maybe_hijack_plugin_info', 0 );
}

/**
 * Filters the tabs shown on the Add Plugins screen.
 *
 * @param string[] $tabs Map of tab ID to map name.
 * @return string[] Updated tabs.
 */
function add_direct_tab( $tabs ) {
	$tabs[ TAB_DIRECT ] = __( 'Direct Install', 'fair' );
	return $tabs;
}

function load_plugin_install() {
	enqueue_assets();

	// Is this our page?
	if ( empty( $_POST['tab'] ) || $_POST['tab'] !== TAB_DIRECT ) {
		return;
	}

	// If the form was submitted, handle it.
	if ( isset( $_POST['plugin_id'] ) ) {
		handle_direct_install();
	}
}

/**
 * Enqueue assets.
 *
 * @param string $hook_suffix Hook suffix for the current admin page.
 * @return void
 */
function enqueue_assets() {
	wp_enqueue_style(
		'fair-admin',
		esc_url( plugin_dir_url( FAIR\PLUGIN_FILE ) . 'assets/css/packages.css' ),
		[],
		FAIR\VERSION
	);
}

function render_tab_direct() {
	?>
	<div class="fair-direct-install">
		<p class="fair-direct-install__help">
			<?= __( "If you have a plugin's ID, enter it here to view the details<br />and install it.", 'fair' ); ?>
		</p>
		<form
			action=""
			class="fair-direct-install__form"
			method="post"
		>
			<input
				type="hidden"
				name="tab"
				value="<?= esc_attr( TAB_DIRECT ) ?>"
			/>
			<?php wp_nonce_field( TAB_DIRECT ); ?>
			<label
				class="screen-reader-text"
				for="plugin_id"
			>Plugin ID</label>
			<input
				type="text"
				id="plugin_id"
				name="plugin_id"
				pattern="did:(web|plc):.+"
				placeholder="did:..."
			/>
			<?php submit_button( _x( 'View Details', 'fair' ), '', '', false ); ?>
		</form>
		<p class="fair-direct-install__note">
			<?= __( 'Plugin IDs should be in the format <code>did:web:...</code> or <code>did:plc:...</code>', 'fair' ); ?>
		</p>
	</div>
	<script>
		// On submit, trigger the thickbox with the plugin information.
		document.querySelector( '.fair-direct-install__form' ).addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			e.stopPropagation();
			const id = document.getElementById( 'plugin_id' ).value;

			// Get the current URL without the query string.
			const currentUrl = new URL( window.location.href );

			// Construct the URL for the plugin information page.
			const baseUrl = currentUrl.origin + currentUrl.pathname;
			const params = new URLSearchParams( {
				tab: 'plugin-information',
				plugin: id,
				TB_iframe: 'true',
				width: '600',
				height: '550'
			} );

			const url = baseUrl + '?' + params.toString();
			tb_show( null, url, false );

			// Set ARIA role, ARIA label, and add a CSS class.
			const tbWindow = jQuery( '#TB_window' );
			tbWindow
				.attr({
					'role': 'dialog',
					'aria-label': wp.i18n.__( 'Plugin details' )
				})
				.addClass( 'plugin-details-modal' );

			// Set title attribute on the iframe.
			tbWindow.find( '#TB_iframeContent' ).attr( 'title', wp.i18n.__( 'Plugin details' ) );
		} );
	</script>
	<?php
}

function handle_direct_install() {
	check_admin_referer( TAB_DIRECT );

	$id = wp_unslash( $_POST['plugin_id'] );

	header( 'Content-Type: text/plain' );
	$res = Packages\install_plugin( $id );
	var_dump( $res );
	exit;
}

function embedded_info_page() {
	// This is a special case for the plugin information page.
	if ( ! isset( $_REQUEST['plugin'] ) || ! isset( $_REQUEST['tab'] ) || $_REQUEST['tab'] !== TAB_DIRECT ) {
		return;
	}

	// If the plugin is not a FAIR package, do nothing.
	if ( ! preg_match( '/^did:(web|plc):.+$/', $_REQUEST['plugin'] ) ) {
		return;
	}

	maybe_hijack_plugin_info();
}

function maybe_hijack_plugin_info() {
	if ( empty( $_REQUEST['plugin'] ) ) {
		return;
	}

	// Hijack, if the plugin is a FAIR package.
	$id = wp_unslash( $_REQUEST['plugin'] );
	if ( ! preg_match( '/^did:(web|plc):.+$/', $id ) ) {
		return;
	}

	$metadata = Packages\fetch_package_metadata( $id );
	if ( is_wp_error( $metadata ) ) {
		wp_die( $metadata->get_error_message() );
	}

	$tab = esc_attr( $GLOBALS['tab'] ?? 'plugin-information' );
	$section = wp_unslash( $_REQUEST['section'] ?? 'description' );

	Info\render_page( $metadata, $tab, $section );
	exit;
}
