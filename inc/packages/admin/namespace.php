<?php
/**
 * Packages admin bootstrap.
 *
 * @package FAIR
 */

namespace FAIR\Packages\Admin;

use FAIR;
use FAIR\Packages;
use FAIR\Packages\MetadataDocument;
use FAIR\Packages\ReleaseDocument;
use FAIR\Updater;

use WP_Upgrader_Skin;

const TAB_DIRECT = 'fair_direct';
const ACTION_INSTALL = 'fair-install-plugin';
const ACTION_INSTALL_NONCE = 'fair-install-plugin';
const ACTION_INSTALL_DID = 'fair-install-did';

/**
 * Bootstrap.
 */
function bootstrap() {
	if ( ! is_admin() ) {
		return;
	}

	add_filter( 'install_plugins_tabs', __NAMESPACE__ . '\\add_direct_tab' );
	add_filter( 'plugins_api', __NAMESPACE__ . '\\handle_did_during_ajax', 10, 3 );
	add_filter( 'upgrader_pre_download', 'FAIR\\Packages\\upgrader_pre_download', 10, 1 );
	add_action( 'install_plugins_' . TAB_DIRECT, __NAMESPACE__ . '\\render_tab_direct' );
	add_action( 'load-plugin-install.php', __NAMESPACE__ . '\\load_plugin_install' );
	add_action( 'install_plugins_pre_plugin-information', __NAMESPACE__ . '\\maybe_hijack_plugin_info', 0 );
	add_action( 'update-custom_' . ACTION_INSTALL, __NAMESPACE__ . '\\handle_direct_install' );
	add_action( 'wp_ajax_check_plugin_dependencies', __NAMESPACE__ . '\\set_slug_to_hashed' );
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

/**
 * Handles the AJAX request for plugin information when a DID is present.
 *
 * @param mixed  $result The result of the plugins_api call.
 * @param string $action The action being performed.
 * @param object $args   The arguments passed to the plugins_api call.
 * @return mixed
 */
function handle_did_during_ajax( $result, $action, $args ) {
	if ( ! wp_doing_ajax() || 'plugin_information' !== $action || ! isset( $args->slug ) ) {
		return $result;
	}

	$slug = sanitize_text_field( $args->slug );
	if ( ! str_contains( $slug, '-did--' ) ) {
		return $result;
	}

	$did = 'did:' . explode( '-did:', str_replace( '--', ':', $slug ), 2 )[1];
	if ( ! preg_match( '/^did:(web|plc):.+$/', $did ) ) {
		return $result;
	}

	wp_cache_set( ACTION_INSTALL_DID, $did );
	Updater\add_package_to_release_cache( $did );
	add_filter( 'http_request_args', 'FAIR\\Updater\\maybe_add_accept_header', 20, 2 );

	return (object) Packages\get_update_data( $did );
}

/**
 * Enqueue assets.
 *
 * @return void
 */
function load_plugin_install() {
	enqueue_assets();
}

/**
 * Enqueue assets.
 *
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

/**
 * Render direct installer tab.
 *
 * @return void
 */
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
				value="<?= esc_attr( TAB_DIRECT ); ?>"
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
				aria-describedby="fair-direct-install__note"
			/>
			<?php submit_button( _x( 'View Details', 'plugin', 'fair' ), '', '', false ); ?>
		</form>
		<p id="fair-direct-install__note">
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

/**
 * Get direct install URL.
 *
 * @param  MetadataDocument $doc Metadata document.
 * @param  ReleaseDocument  $release Release document.
 *
 * @return string
 */
function get_direct_install_url( MetadataDocument $doc, ReleaseDocument $release ) {
	$args = [
		'action' => ACTION_INSTALL,
		'id' => urlencode( $doc->id ),
		'version' => urlencode( $release->version ),
	];
	$url = add_query_arg( $args, self_admin_url( 'update.php' ) );
	return wp_nonce_url( $url, ACTION_INSTALL_NONCE . $doc->id );
}

/**
 * Get direct update URL.
 *
 * @param  MetadataDocument $doc Metadata document.
 *
 * @return string
 */
function get_direct_update_url( MetadataDocument $doc ): string {
	$type = str_replace( 'wp-', '', $doc->type );
	$action = "upgrade-{$type}";
	$packages = Updater\get_packages();
	$file = $packages[ "{$type}s" ][ $doc->id ];
	$file = $type === 'plugin' ? plugin_basename( $file ) : basename( dirname( $file ) );
	$args = [
		'action' => $action,
		$type => $file,
	];
	$url = add_query_arg( $args, self_admin_url( 'update.php' ) );
	return wp_nonce_url( $url, "{$action}_{$file}" );
}

/**
 * Handle direct install.
 *
 * @return void
 */
function handle_direct_install() {
	$id = sanitize_text_field( wp_unslash( $_GET['id'] ?? null ) );
	check_admin_referer( ACTION_INSTALL_NONCE . $id );

	$version = sanitize_text_field( wp_unslash( $_GET['version'] ?? null ) );
	if ( empty( $version ) ) {
		wp_die( __( 'No version specified for the plugin.', 'fair' ) );
	}

	$skin = new WP_Upgrader_Skin();
	Packages\install_plugin( $id, $skin, $version );
	exit;
}

/**
 * Set slug to hashed slug from escaped slug-did.
 *
 * Needed for check_plugin_dependencies_during_ajax().
 *
 * @return void
 */
function set_slug_to_hashed() : void {
	// phpcs:disable HM.Security.NonceVerification.Missing
	if ( ! isset( $_POST['slug'] ) ) {
		return;
	}

	$escaped_slug = sanitize_text_field( wp_unslash( $_POST['slug'] ) );
	$did = 'did:' . explode( '-did:', str_replace( '--', ':', $escaped_slug ), 2 )[1];
	if ( ! preg_match( '/^did:(web|plc):.+$/', $did ) ) {
		return;
	}

	// Reset to proper hashed slug.
	$_POST['slug'] = explode( '-did--', $escaped_slug, 2 )[0] . '-' . Packages\get_did_hash( $did );
	// phpcs:enable
}

/**
 * Hijack embedded info page.
 *
 * @return void
 */
function embedded_info_page() {
	// phpcs:disable HM.Security.NonceVerification.Recommended
	// This is a special case for the plugin information page.
	if ( ! isset( $_REQUEST['plugin'] ) || ! isset( $_REQUEST['tab'] ) || $_REQUEST['tab'] !== TAB_DIRECT ) {
		return;
	}

	// If the plugin is not a FAIR package, do nothing.
	if ( ! preg_match( '/^did:(web|plc):.+$/', sanitize_text_field( wp_unslash( $_REQUEST['plugin'] ) ) ) ) {
		return;
	}
	// phpcs:enable

	maybe_hijack_plugin_info();
}

/**
 * Maybe hijack plugin info.
 *
 * @return void
 */
function maybe_hijack_plugin_info() {
	// phpcs:disable HM.Security.NonceVerification.Recommended
	if ( empty( $_REQUEST['plugin'] ) ) {
		return;
	}

	// Hijack, if the plugin is a FAIR package.
	$id = sanitize_text_field( wp_unslash( $_REQUEST['plugin'] ) );
	if ( ! preg_match( '/^did:(web|plc):.+$/', $id ) ) {
		return;
	}

	$metadata = Packages\fetch_package_metadata( $id );
	if ( is_wp_error( $metadata ) ) {
		wp_die( esc_html( $metadata->get_error_message() ) );
	}

	$tab = esc_attr( $GLOBALS['tab'] ?? 'plugin-information' );
	$section = isset( $_REQUEST['section'] ) ? sanitize_key( wp_unslash( $_REQUEST['section'] ) ) : 'description';
	// phpcs:enable

	Info\render_page( $metadata, $tab, $section );
	exit;
}
