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
use WP_Error;

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
	add_filter( 'plugins_api', __NAMESPACE__ . '\\search_by_did', 10, 3 );
	add_filter( 'upgrader_pre_download', 'FAIR\\Packages\\upgrader_pre_download', 10, 1 );
	add_action( 'install_plugins_' . TAB_DIRECT, __NAMESPACE__ . '\\render_tab_direct' );
	add_action( 'load-plugin-install.php', __NAMESPACE__ . '\\load_plugin_install' );
	add_action( 'install_plugins_pre_plugin-information', __NAMESPACE__ . '\\maybe_hijack_plugin_info', 0 );
	add_filter( 'plugins_api_result', __NAMESPACE__ . '\\alter_slugs', 10, 3 );
	add_filter( 'plugins_api_result', __NAMESPACE__ . '\\order_plugin_information_sections', 11, 2 );
	add_filter( 'plugin_install_action_links', __NAMESPACE__ . '\\maybe_hijack_plugin_install_button', 10, 2 );
	add_filter( 'plugin_install_description', __NAMESPACE__ . '\\maybe_add_data_to_description', 10, 2 );
	add_action( 'wp_ajax_check_plugin_dependencies', __NAMESPACE__ . '\\set_slug_to_hashed' );
	add_filter( 'wp_list_table_class_name', __NAMESPACE__ . '\\maybe_override_list_table' );
}

/**
 * Override the install list table with our own.
 *
 * @param string $class_name List table class name to use.
 * @return string Overridden class name.
 */
function maybe_override_list_table( $class_name ) {
	if ( $class_name !== 'WP_Plugin_Install_List_Table' ) {
		return $class_name;
	}

	// Load list table class. We must do this here, as WP_List_Table isn't loaded by default.
	require_once ABSPATH . 'wp-admin/includes/class-wp-plugin-install-list-table.php';
	require_once __DIR__ . '/class-list-table.php';
	return List_Table::class;
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
	if ( ! preg_match( '/^did:plc:.+$/', $did ) ) {
		return $result;
	}

	( new Updater\Updater( $did ) )->run();

	set_transient( ACTION_INSTALL_DID, $did );
	Packages\add_package_to_release_cache( $did );
	add_filter( 'http_request_args', 'FAIR\\Packages\\maybe_add_accept_header', 20, 2 );

	return (object) Packages\get_update_data( $did );
}

/**
 * Enable searching by DID.
 *
 * @param mixed  $result The result of the plugins_api call.
 * @param string $action The action being performed.
 * @param object $args   The arguments passed to the plugins_api call.
 * @return mixed
 */
function search_by_did( $result, $action, $args ) {
	if ( 'query_plugins' !== $action || empty( $args->search ) ) {
		return $result;
	}

	// The DID comes from a URL-encoded request parameter, and must be decoded first.
	$did = sanitize_text_field( urldecode( $args->search ) );
	if ( ! str_starts_with( $did, 'did:plc:' ) || strlen( $did ) !== 32 ) {
		return $result;
	}

	$api_data = Packages\get_update_data( $did );
	if ( is_wp_error( $api_data ) ) {
		return $result;
	}

	$api_data = json_decode( json_encode( $api_data ), true );
	$api_data['description'] = $api_data['sections']['description'];
	$api_data['short_description'] = substr( strip_tags( trim( $api_data['description'] ) ), 0, 147 ) . '...';
	$api_data['last_updated'] ??= 0;
	$api_data['num_ratings'] ??= 0;
	$api_data['rating'] ??= 0;
	$api_data['active_installs'] ??= 0;

	// Avoid a double-hashed slug.
	$hash_suffix = '-' . Packages\get_did_hash( $did );
	if ( str_ends_with( $api_data['slug'], $hash_suffix ) ) {
		$api_data['slug'] = str_replace( $hash_suffix, '', $api_data['slug'] );
	}

	$result = [
		'plugins' => [ $api_data ],
		'info' => [
			'page' => 1,
			'pages' => 1,
			'results' => 1,
			'total' => 1,
		],
	];

	return (object) $result;
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
			<?= __( 'Enter a plugin ID to view details and install.', 'fair' ); ?>
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
				pattern="did:plc:.+"
				placeholder="did:plc:..."
				required
				aria-describedby="fair-direct-install__note"
			/>
			<?php submit_button( _x( 'View Details', 'plugin', 'fair' ), '', '', false ); ?>
		</form>
		<p id="fair-direct-install__note">
			<?= __( 'Plugin IDs should be in the format <code>did:plc:...</code>', 'fair' ); ?>
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
 * Set slug to hashed slug from escaped slug-did.
 *
 * Needed for check_plugin_dependencies_during_ajax().
 *
 * @return void
 */
function set_slug_to_hashed() : void {
	check_ajax_referer( 'updates' );

	if ( ! isset( $_POST['slug'] ) ) {
		return;
	}

	$escaped_slug = sanitize_text_field( wp_unslash( $_POST['slug'] ) );
	$did = 'did:' . explode( '-did:', str_replace( '--', ':', $escaped_slug ), 2 )[1];
	if ( ! preg_match( '/^did:plc:.+$/', $did ) ) {
		return;
	}

	// Reset to proper hashed slug.
	$_POST['slug'] = explode( '-did--', $escaped_slug, 2 )[0] . '-' . Packages\get_did_hash( $did );
}

/**
 * Check if this is a FAIR plugin, for legacy data.
 *
 * FAIR data is bridged into legacy data via the _fair property, and needs
 * to have a valid DID. We can use this to enhance our existing metadata.
 *
 * @param array|stdClass $api_data Legacy dotorg-formatted data to check.
 * @return bool
 */
function is_fair_plugin( $api_data ) : bool {
	$api = (array) $api_data;
	if ( empty( $api['_fair'] ) ) {
		return false;
	}

	$fair_data = (array) $api['_fair'];
	if ( empty( $fair_data['id'] ) ) {
		return false;
	}

	// Is this a fake bridged plugin?
	return str_starts_with( $fair_data['id'], 'did:' );
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
	if ( ! preg_match( '/^did:plc:.+$/', $id ) ) {
		if ( str_contains( $id, '-did--' ) ) {
			// Bridged. Convert back to a DID.
			$split = explode( '-did--', $id, 2 );
			$id = 'did:' . str_replace( '--', ':', $split[1] );
		} else {
			// See if this a transparently-upgraded plugin.
			maybe_hijack_legacy_plugin_info();
			return;
		}
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

/**
 * Maybe hijack the legacy plugin info too.
 */
function maybe_hijack_legacy_plugin_info() {
	// phpcs:disable HM.Security.NonceVerification.Recommended
	if ( empty( $_REQUEST['plugin'] ) ) {
		return;
	}

	$api = plugins_api(
		'plugin_information',
		[
			'slug' => sanitize_text_field( wp_unslash( $_REQUEST['plugin'] ) ),
		]
	);

	if ( is_wp_error( $api ) ) {
		// phpcs:ignore HM.Security.EscapeOutput.OutputNotEscaped -- Escaping not necessary for WP_Error.
		wp_die( $api );
	}

	// Is this a FAIR plugin, actually?
	if ( ! is_fair_plugin( $api ) ) {
		return;
	}

	// Neat! Upgrade it.
	// We need to convert deeply to objects, so re-encode as JSON.
	$reencoded = json_decode( json_encode( $api->_fair ), false );
	$metadata = MetadataDocument::from_data( $reencoded );
	if ( is_wp_error( $metadata ) ) {
		wp_die( esc_html( $metadata->get_error_message() ) );
	}

	$tab = esc_attr( $GLOBALS['tab'] ?? 'plugin-information' );
	$section = isset( $_REQUEST['section'] ) ? sanitize_key( wp_unslash( $_REQUEST['section'] ) ) : 'description';
	Info\render_page( $metadata, $tab, $section );
	exit;
}

/**
 * Filters the Plugin Installation API response results.
 *
 * @since 2.7.0
 *
 * @param object|WP_Error $res    Response object or WP_Error.
 * @param string          $action The type of information being requested from the Plugin Installation API.
 * @param object          $args   Plugin API arguments.
 */
function alter_slugs( $res, $action, $args ) {
	if ( 'query_plugins' !== $action ) {
		return $res;
	}

	if ( empty( $res->plugins ) ) {
		return $res;
	}

	// Alter the slugs to our globally unique version.
	foreach ( $res->plugins as &$plugin ) {
		if ( ! is_fair_plugin( $plugin ) ) {
			continue;
		}

		$did = $plugin['_fair']['id'];
		$plugin['slug'] = esc_attr( $plugin['slug'] . '-' . str_replace( ':', '--', $did ) );
	}

	return $res;
}

/**
 * Order the sections of Plugin Installation API response.
 *
 * @param object|WP_Error $res    Response object or WP_Error.
 * @param string          $action The type of information being requested from the Plugin Installation API.
 */
function order_plugin_information_sections( $res, $action ) {
	if ( is_wp_error( $res ) ) {
		return $res;
	}

	if ( 'plugin_information' !== $action ) {
		return $res;
	}

	if ( empty( $res->sections ) ) {
		return $res;
	}

	$res->sections = order_sections_by_predefined_order( $res->sections );

	return $res;
}

/**
 * Order sections by a predefined order.
 *
 * @param array<string, string> $sections Sections to order.
 *
 * @return array<string, string> Ordered sections.
 */
function order_sections_by_predefined_order( array $sections ) : array {
	$desired_order = [
		'description',
		'installation',
		'faq',
		'screenshots',
		'changelog',
		'security',
		'reviews',
		'other_notes',
	];

	$desired_order_index = array_flip( $desired_order );

	uksort($sections, function( $a, $b ) use ( $desired_order_index ) {
		$pos_a = $desired_order_index[ $a ] ?? PHP_INT_MAX;
		$pos_b = $desired_order_index[ $b ] ?? PHP_INT_MAX;

		return $pos_a <=> $pos_b;
	});

	return $sections;
}

/**
 * Override the install button, for bridged plugins.
 *
 * Bridged plugins appear in the API (via `alter_slugs()`) with slugs like
 * `plugin-name-did--method--msid`, however they are installed to
 * `plugin-name-didhash`. In order to show the correct button, we need to check
 * against the install slug, not the API slug.
 *
 * @param string[] $links List of action links
 * @param array $plugin (Legacy) plugin data from the dotorg API.
 * @return array Altered actions.
 */
function maybe_hijack_plugin_install_button( $links, $plugin ) {
	if ( ! is_fair_plugin( $plugin ) || ! str_contains( $plugin['slug'], '-did--' ) ) {
		return $links;
	}

	$did = $plugin['_fair']['id'];

	// Find the action button(s).
	foreach ( $links as &$link ) {
		if ( ! str_contains( $link, 'data-slug="' ) ) {
			continue;
		}

		// Found it. Override the install button with ours.
		// Is the plugin actually installed?
		$plugin_override = (array) $plugin;
		$plugin_override['slug'] = $plugin['_fair']['slug'] . '-' . Packages\get_did_hash( $did );
		$status = install_plugin_install_status( $plugin_override );
		if ( $status['status'] === 'install' ) {
			// Not installed, so don't alter anything.
			return $links;
		}

		// Installed, regenerate the button.
		$requires_php = isset( $plugin['requires_php'] ) ? $plugin['requires_php'] : null;
		$requires_wp  = isset( $plugin['requires'] ) ? $plugin['requires'] : null;
		$compatible_php = is_php_version_compatible( $requires_php );
		$compatible_wp  = is_wp_version_compatible( $requires_wp );
		$name = strip_tags( $plugin['name'] . ' ' . $plugin['version'] );
		$link = wp_get_plugin_action_button( $name, $plugin_override, $compatible_php, $compatible_wp );
	}
	return $links;
}

/**
 * Filters the plugin card description on the Add Plugins screen.
 *
 * @param string $description Plugin card description.
 * @param array $plugin (Legacy) plugin data from the dotorg API.
 * @return string Plugin card description.
 */
function maybe_add_data_to_description( $description, $plugin ) {
	if ( ! is_fair_plugin( $plugin ) ) {
		return $description;
	}

	$did = $plugin['_fair']['id'];
	$repo_host = Info\get_repository_hostname( $did );
	if ( empty( $repo_host ) ) {
		return $description;
	}

	/* translators: %1$s: repository hostname */
	$description .= '</p><p class="authors"><em>' . sprintf( __( 'Hosted on %1$s', 'fair' ), esc_html( $repo_host ) ) . '</em>';
	return $description;
}
