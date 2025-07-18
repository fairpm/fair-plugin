<?php
/**
 * Adds a local source for avatars.
 *
 * @package FAIR
 */

namespace FAIR\Avatars;

const AVATAR_SRC_SETTING_KEY = 'fair_avatar_source';

/**
 * Bootstrap.
 */
function bootstrap() {
	$avatar_source = get_site_option( AVATAR_SRC_SETTING_KEY, 'fair' );

	if ( 'fair' !== $avatar_source ) {
		return;
	}

	// Add avatar upload field to user profile.
	add_filter( 'user_profile_picture_description', __NAMESPACE__ . '\\add_avatar_upload_field', 10, 2 );

	// Save avatar upload.
	add_action( 'personal_options_update', __NAMESPACE__ . '\\save_avatar_upload' );
	add_action( 'edit_user_profile_update', __NAMESPACE__ . '\\save_avatar_upload' );

	// Filter avatar retrieval.
	add_filter( 'get_avatar', __NAMESPACE__ . '\\filter_avatar', 10, 6 );
	add_filter( 'get_avatar_url', __NAMESPACE__ . '\\filter_avatar_url', 10, 3 );
	add_filter( 'avatar_defaults', '__return_empty_array' );

	// Enqueue media scripts.
	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_media_scripts' );
}

/**
 * Enqueue media scripts and some CSS.
 *
 * @param string $hook_suffix The current admin page.
 */
function enqueue_media_scripts( $hook_suffix ) {

	if ( 'profile.php' !== $hook_suffix && 'user-edit.php' !== $hook_suffix ) {
		return;
	}

	// Grab the user ID to pass along for alt text.
	$user_id = 'profile.php' === $hook_suffix ? get_current_user_id() : absint( $_GET['user_id'] ?? 0 );
	$display_name = get_user( $user_id )->display_name;

	wp_enqueue_media();
	wp_enqueue_script( 'fair-avatars', esc_url( plugin_dir_url( \FAIR\PLUGIN_FILE ) . 'assets/js/fair-avatars.js' ), [ 'jquery', 'wp-a11y', 'wp-i18n' ], \FAIR\VERSION, true );
	wp_localize_script( 'fair-avatars', 'fairAvatars',
		[
			'defaultImg' => generate_default_avatar( $display_name ),
			'defaultAlt' => get_avatar_alt( $user_id ),
		]
	);

	// Some inline CSS for our fields.
	$setup_css = '
		span.fair-avatar-desc {
			display: block;
			margin-top: 5px;
		}
		input#fair-avatar-remove {
			margin-left: 5px;
		}
		input.button.button-hidden {
			display: none;
		}
	';

	// And add the CSS.
	wp_add_inline_style( 'common', $setup_css );
}

/**
 * Add avatar upload field to user profile.
 *
 * @param  string  $description  Default description.
 * @param  WP_User $profile_user The user object being used on the profile.
 *
 * @return string              Modified description with upload fields.
 */
function add_avatar_upload_field( $description, $profile_user ) {
	if ( ! current_user_can( 'upload_files' ) ) {
		return $description;
	}

	$avatar_id = get_user_meta( $profile_user->ID, 'fair_avatar_id', true );

	// Set a class based on an avatar being there right now.
	$remove_cls = $avatar_id ? 'button' : 'button button-hidden';

	echo '<input type="hidden" name="fair_avatar_id" id="fair-avatar-id" value="' . absint( $avatar_id ) . '" />';
	echo '<input type="button" class="button" id="fair-avatar-upload" value="' . esc_attr__( 'Choose Profile Image', 'fair' ) . '" />';
	echo '<input type="button" class="' . esc_attr( $remove_cls ) . '" id="fair-avatar-remove" value="' . esc_attr__( 'Remove Profile Image', 'fair' ) . '" />';

	// Using a span because this entire area is dropped into a `<p>` tag.
	echo '<span class="fair-avatar-desc">' . esc_html__( 'Upload a custom profile picture for your account.', 'fair' ) . '</span>';

	return;
}

/**
 * Save or delete avatar ID.
 *
 * @param int $user_id User ID.
 */
function save_avatar_upload( $user_id ) {
	check_admin_referer( 'update-user_' . $user_id );

	if ( ! current_user_can( 'edit_user', $user_id ) ) {
		return;
	}

	$fair_avatar_id = isset( $_POST['fair_avatar_id'] ) ? absint( $_POST['fair_avatar_id'] ) : 0;

	if ( ! empty( $fair_avatar_id ) ) {
		// Store the site ID to check on multisite.
		// Stored on all sites in case site is converted to multisite.
		update_user_meta( $user_id, 'fair_avatar_site_id', get_current_blog_id() );
		update_user_meta( $user_id, 'fair_avatar_id', $fair_avatar_id );
	} else {
		delete_user_meta( $user_id, 'fair_avatar_site_id', get_current_blog_id() );
		delete_user_meta( $user_id, 'fair_avatar_id' );
	}
}

/**
 * Filter avatar HTML.
 *
 * @param  string $avatar      Avatar HTML.
 * @param  mixed  $id_or_email User ID, email, or comment object.
 * @param  int    $size        Avatar size.
 * @param  string $default     Default avatar URL.
 * @param  string $alt         Alt text.
 * @param  array  $args        Avatar arguments.
 *
 * @return string              Filtered avatar HTML.
 */
function filter_avatar( $avatar, $id_or_email, $size, $default, $alt, $args ) {
	$avatar_url = get_avatar_url( $id_or_email, $args );

	$class = [ 'avatar', 'avatar-' . (int) $size, 'photo' ];
	if ( ! empty( $args['class'] ) ) {
		$class = array_merge( $class, (array) $args['class'] );
	}

	$extra_args = ! empty( $args['extra_attr'] ) ? $args['extra_attr'] : '';

	if ( ! empty( $args['loading'] ) ) {
		$extra_args .= 'loading="' . sanitize_text_field( $args['loading'] ) . '" ';
	}

	if ( ! empty( $args['decoding'] ) ) {
		$extra_args .= 'decoding="' . sanitize_text_field( $args['decoding'] ) . '" ';
	}

	if ( empty( $alt ) && is_int( $id_or_email ) ) {
		$alt = get_avatar_alt( $id_or_email );
	}

	return sprintf(
		"<img alt='%s' src='%s' class='%s' height='%d' width='%d' %s/>",
		esc_attr( $alt ),
		esc_url( $avatar_url, [ 'http', 'https', 'data' ] ),
		esc_attr( implode( ' ', $class ) ),
		(int) $size,
		(int) $size,
		esc_attr( $extra_args )
	);
}

/**
 * Filter avatar URL.
 *
 * @param  string $url         Avatar URL.
 * @param  mixed  $id_or_email User ID, email, or comment object.
 * @param  array  $args        Avatar arguments.
 *
 * @return string              Filtered avatar URL.
 */
function filter_avatar_url( $url, $id_or_email, $args ) {
	return get_avatar_url( $id_or_email, $args );
}

/**
 * Get avatar URL.
 *
 * @param  mixed  $id_or_email User ID, email, or comment object.
 * @param  array  $args        Avatar arguments.
 *
 * @return string              Filtered avatar URL.
 */
function get_avatar_url( $id_or_email, $args ) {
	$user = false;

	if ( is_numeric( $id_or_email ) ) {
		$user = get_user_by( 'id', absint( $id_or_email ) );
	} elseif ( is_string( $id_or_email ) ) {
		$user = get_user_by( 'email', $id_or_email );
	} elseif ( $id_or_email instanceof \WP_User ) {
		$user = $id_or_email;
	} elseif ( $id_or_email instanceof \WP_Comment ) {
		$user = get_user_by( 'id', $id_or_email->user_id );

		// Special-case for comments.
		if ( ! $user ) {
			return generate_default_avatar( $id_or_email->comment_author );
		}
	}

	if ( ! $user ) {
		return generate_default_avatar( '?' );
	}

	$avatar_id = get_user_meta( $user->ID, 'fair_avatar_id', true );
	if ( ! $avatar_id ) {
		return generate_default_avatar( $user->display_name );
	}

	$size = isset( $args['size'] ) ? (int) $args['size'] : 150;
	$switched = false;
	if ( is_multisite() ) {
		$switched = true;
		$user_site = get_user_meta( $user->ID, 'fair_avatar_site_id', true );
		switch_to_blog( $user_site );
	}
	$avatar_url = wp_get_attachment_image_url( $avatar_id, [ $size, $size ] );
	if ( true === $switched ) {
		restore_current_blog();
	}

	return $avatar_url ? $avatar_url : '';
}

/**
 * Get the default avatar alt text.
 *
 * @param  mixed  $id_or_email User ID, email, or comment object.
 *
 * @return string              Filtered avatar URL.
 */
function get_avatar_alt( $id_or_email ) {
	// Comments use the author name, rather than the user's display name.
	if ( $id_or_email instanceof \WP_Comment ) {
		/* translators: %s: Name of person in profile picture */
		return sprintf( __( 'profile picture for %s', 'fair' ), $id_or_email->comment_author );
	}

	if ( is_numeric( $id_or_email ) ) {
		$user = get_user_by( 'id', absint( $id_or_email ) );
	} elseif ( is_string( $id_or_email ) ) {
		$user = get_user_by( 'email', $id_or_email );
	} elseif ( $id_or_email instanceof \WP_User ) {
		$user = $id_or_email;
	}

	if ( ! $user ) {
		return _x( 'profile picture for user', 'alt for unknown avatar user', 'fair' );
	}

	/* translators: %s: Name of person in profile picture */
	return sprintf( __( 'profile picture for %s', 'fair' ), $user->display_name );
}

/**
 * Get the default avatar URL.
 *
 * @param  string|null $name Name to derive avatar from.
 *
 * @return string            Default avatar URL.
 */
function generate_default_avatar( ?string $name = null ) : string {
	$first = strtoupper( substr( $name ?? '', 0, 1 ) );

	$tmpl = <<<"END"
		<?xml version="1.0" encoding="UTF-8"?>
		<svg version="1.1" xmlns="http://www.w3.org/2000/svg" width="50" height="50" viewBox="0 0 50 50">
			<rect width="100%%" height="100%%" fill="%s"/>
			<text
				fill="#fff"
				font-family="sans-serif"
				font-size="26"
				font-weight="500"
				dominant-baseline="middle"
				text-anchor="middle"
				x="50%%"
				y="55%%"
			>
				%s
			</text>
		</svg>
		END;

	/**
	 * Filter the background color for the default avatar.
	 *
	 * Default is the placeholder color from .wp-color-picker (#646970).
	 *
	 * @param string $color Default color.
	 * @param string $name  Name to derive avatar from.
	 */
	$color = add_filter( 'fair_avatars_default_color', '#646970', $name );
	$data = sprintf(
		$tmpl,
		esc_attr( $color ),
		esc_xml( $first )
	);

	/**
	 * Filter the default avatar.
	 *
	 * This is an SVG string, which is encoded into a data: URI.
	 *
	 * @param string $data Default avatar SVG.
	 * @param string $name Name to derive avatar from.
	 */
	$data = apply_filters( 'fair_avatars_default_svg', $data, $name );

	$uri = 'data:image/svg+xml;base64,' . base64_encode( $data );
	return $uri;
}
