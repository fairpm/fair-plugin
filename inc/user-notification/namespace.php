<?php
/**
 * Notify users that updates are served from FAIR/AspirePress.
 *
 * @package FAIR
 */

namespace FAIR\User_Notification;

/**
 * Bootstrap.
 */
function bootstrap() {
	add_filter( 'update_footer', __NAMESPACE__ . '\\notify_users', 11, 1 );
	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_global_styles' );
}

/**
 * Add a notification to the site footer about FAIR/AspirePress.
 *
 * @param string|null $content The current version or update notification.
 * @return string
 */
function notify_users( ?string $content = null ) : string {
	$message = sprintf(
		// translators: 1) Fair PM URL, 2) AspirePress URL.
		__( 'Updates served from the <a href="%1$s">FAIR Package Manager</a> and <a href="%2$s">AspirePress</a>', 'fair' ),
		'https://fair.pm',
		'https://aspirepress.org'
	);
	$notification = '<span class="fair-notification">' . $message . '</span>';

	return $content . $notification;
}

/**
 * Enqueue global style assets.
 *
 * @param string $hook_suffix Hook suffix for the current admin page.
 * @return void
 */
function enqueue_global_styles( string $hook_suffix ) {
	wp_enqueue_style(
		'fair-global-admin',
		esc_url( plugin_dir_url( \FAIR\PLUGIN_FILE ) . 'assets/css/global-admin.css' ),
		[],
		\FAIR\VERSION
	);
}
