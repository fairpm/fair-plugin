<?php

namespace FAIR\Packages\Admin;

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
	// Is this our page?
	if ( empty( $_POST['tab'] ) || $_POST['tab'] !== TAB_DIRECT ) {
		return;
	}

	// If the form was submitted, handle it.
	if ( isset( $_POST['plugin_id'] ) ) {
		handle_direct_install();
	}
}

function render_tab_direct() {
	?>
	<div class="fair-direct">
		<p class="install-help">
			<?php _e( "If you have a plugin's ID, you may install it here.", 'fair' ); ?>
		</p>
		<form
			action=""
			class=""
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
				type="name"
				id="plugin_id"
				name="plugin_id"
				pattern="did:(web|plc):.+"
			/>
			<?php submit_button( _x( 'Install Now', 'plugin' ), '', '', false ); ?>
		</form>
	</div>
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
