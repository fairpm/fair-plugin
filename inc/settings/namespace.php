<?php
/**
 * Implements the plugin settings page.
 *
 * @package FAIR
 */

namespace FAIR\Settings;

/**
 * Bootstrap.
 */
function bootstrap() {
	add_action( 'admin_menu', __NAMESPACE__ . '\\create_settings_menu' );
	add_action( 'admin_notices', __NAMESPACE__ . '\\display_settings_saved_notice' );
	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_scripts_and_styles' );
}

/**
 * Enqueue assets.
 *
 * @param string $hook_suffix Hook suffix for the current admin page.
 * @return void
 */
function enqueue_scripts_and_styles( string $hook_suffix ) {

	if ( 'toplevel_page_fair-settings' !== $hook_suffix ) {
		return;
	}

	wp_enqueue_style(
		'fair-admin',
		esc_url( plugin_dir_url( \FAIR\PLUGIN_FILE ) . 'assets/css/admin.css' ),
		[],
		\FAIR\VERSION
	);
}

/**
 * Create the settings menu.
 *
 * @return void
 */
function create_settings_menu() {
	add_menu_page(
		__( 'FAIR Settings', 'fair' ),
		__( 'FAIR Settings', 'fair' ),
		'manage_options',
		'fair-settings',
		__NAMESPACE__ . '\\render_settings_page',
	);
}

/**
 * Render the settings page.
 */
function render_settings_page() {
	// Check user permissions.
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'fair' ) );
	}

	if ( save_settings() ) {
		set_transient( 'fair_settings_saved', true, 30 );
	}

	$settings = get_option( 'fair_settings', [] );

	?>
	<div class="wrap fair-settings">
		<h1><?php esc_html_e( 'FAIR Settings', 'fair' ); ?></h1>
		<p><?php printf(
			/* translators: %s: AspirePress website URL */
			esc_html__( 'Your site is now disconnected from WordPress.org managed servers and uses a dedicated mirror from %s. In the coming weeks and months, we\'ll be making the mirror configurable and add in the ability to add your own.', 'fair' ),
			'<a href="https://aspirepress.org/">AspirePress</a>'
		); ?></p>
		<p><?php printf(
			/* translators: %1$s: FAIR GitHub URL, %2$s: FAQ URL */
			esc_html__( 'If you want to learn more, check out the %1$s and our %2$s.', 'fair' ),
			'<a href="https://fair.pm/">FAIR GitHub</a>',
			'<a href="https://github.com/fairpm/tsc/blob/main/faqs/README.md">FAQ</a>'
		); ?></p>
		<form method="post">
			<?php wp_nonce_field( 'fair_save_settings' ); ?>
			<?php render_avatar_setting( $settings ); ?>
			<?php submit_button( __( 'Save Settings', 'fair' ), 'primary', 'fair_settings_submit' ); ?>
		</form>
	</div>
	<?php
}

/**
 * Render the avatar source setting.
 *
 * @param array $settings The current settings options.
 * @return void
 */
function render_avatar_setting( array $settings = [] ) {
	$available_sources = get_avatar_sources();

	$source = array_key_exists( 'avatar_source', $settings ) && array_key_exists( $settings['avatar_source'], $available_sources )
		? $settings['avatar_source']
		: array_key_first( $available_sources );

	?>
	<h2 class="title">
		<?php esc_html_e( 'Avatar Settings', 'fair' ); ?>
	</h2>
	<div class="row">
		<div class="label-wrapper">
			<label for="fair-avatar-source">
				<?php esc_html_e( 'Avatar Source', 'fair' ); ?>
			</label>
		</div>
		<div class="field">
			<select id="fair-avatar-source" name="fair_settings[avatar_source]" aria-describedby="fair-avatar-source-description">
				<?php foreach ( $available_sources as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $source, $key ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<p class="description" id="fair-avatar-source-description">
				<?php esc_html_e( 'Avatars will be loaded from the selected source.', 'fair' ); ?>
			</p>
		</div>
	</div>
	<?php
}

/**
 * Save settings.
 *
 * @return bool
 */
function save_settings() : bool {
	if ( ! isset( $_POST['fair_settings'] ) || ! check_admin_referer( 'fair_save_settings' ) ) {
		return false;
	}

	$raw = is_array( $_POST['fair_settings'] ) ? $_POST['fair_settings'] : [];

	$settings = get_option( 'fair_settings', [] );

	// Avatar source.
	$avatar_sources = get_avatar_sources();

	// Ensure the 'avatar_source' key exists and is a valid option.
	$avatar_source = $raw['avatar_source'] ?? array_key_first( $avatar_sources );
	if ( array_key_exists( $avatar_source, $avatar_sources ) ) {
		$settings['avatar_source'] = $avatar_source;
	}

	// Update the settings option.
	update_option( 'fair_settings', $settings, false );

	return true;
}

/**
 * Get the available avatar sources.
 *
 * @return array
 */
function get_avatar_sources() : array {
	return [
		'fair'     => __( 'FAIR Avatars', 'fair' ),
		'gravatar' => __( 'Gravatar', 'fair' ),
	];
}

/**
 * Display settings saved notice.
 *
 * @return void
 */
function display_settings_saved_notice() {
	if ( get_transient( 'fair_settings_saved' ) ) {
		delete_transient( 'fair_settings_saved' );

		echo '<div class="notice notice-success is-dismissible"><p>'
			. esc_html__( 'Settings saved successfully.', 'fair' )
			. '</p></div>';
	}
}
