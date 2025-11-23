<?php
/**
 * Implements the plugin settings page.
 *
 * @package FAIR
 */

namespace FAIR\Settings;

use const FAIR\Avatars\AVATAR_SRC_SETTING_KEY;

/**
 * Bootstrap.
 */
function bootstrap() {
	add_action( 'admin_init', __NAMESPACE__ . '\\load_single_site_avatar_settings' );
	add_action( 'wpmu_options', __NAMESPACE__ . '\\load_multisite_avatar_settings' );
	add_action( 'update_wpmu_options', __NAMESPACE__ . '\save_multisite_avatar_settings' );
}

/**
 * Register the single site settings fields.
 *
 * @return void
 */
function load_single_site_avatar_settings() {

	// Don't set this up if we're on a multisite.
	if ( defined( 'MULTISITE' ) && false !== MULTISITE ) {
		return;
	}

	$setup_args = [
		'type'              => 'string',
		'sanitize_callback' => 'sanitize_text_field',
		'default'           => 'fair',
		'show_in_rest'      => false,
	];

	register_setting( 'discussion', AVATAR_SRC_SETTING_KEY, $setup_args );

	$field_args = get_avatar_source_field_args();

	add_settings_field( AVATAR_SRC_SETTING_KEY, __( 'Avatar Source', 'fair' ), __NAMESPACE__ . '\\site_avatar_source_field', 'discussion', 'avatars', $field_args );
}

/**
 * Register the multisite settings fields.
 *
 * @return void
 */
function load_multisite_avatar_settings() {

	$field_args = get_avatar_source_field_args();

	echo '<h2>' . esc_html__( 'FAIR Settings', 'fair' ) . '</h2>';

	echo '<table class="form-table" role="presentation">';
		echo '<tr>';
			echo '<th>';
				echo '<label for="' . esc_attr( $field_args['label_for'] ) . '">' . esc_html( $field_args['label'] ) . '</label>';
			echo '</th>';

			echo '<td>';
				site_avatar_source_field( $field_args );
			echo '</td>';

		echo '</tr>';
	echo '</table>';
}

/**
 * Save the option being passed from the multisite settings.
 *
 * @return void
 */
function save_multisite_avatar_settings() {

	$avatar_passed  = filter_input( INPUT_POST, AVATAR_SRC_SETTING_KEY, FILTER_SANITIZE_SPECIAL_CHARS );
	$avatar_sources = array_keys( get_avatar_sources() );
	$avatar_source  = ! empty( $avatar_passed ) && in_array( $avatar_passed, $avatar_sources, true ) ? $avatar_passed : 'fair';

	update_site_option( AVATAR_SRC_SETTING_KEY, $avatar_source );
}

/**
 * Our dropdown to select the avatar source on a single site.
 *
 * @param  array $args  The args passed from the `add_settings_field` call or our own.
 *
 * @return void
 */
function site_avatar_source_field( $args ) : void {

	// The rest of the table markup is there, so begin with the select.
	echo '<select id="' . esc_attr( $args['field_id'] ) . '" name="' . esc_attr( $args['field_name'] ) . '" aria-describedby="fair-avatar-source-description">';

	foreach ( get_avatar_sources() as $source_key => $source_label ) {
		echo '<option value="' . esc_attr( $source_key ) . '" ' . selected( $args['value'], $source_key, false ) . '>' . esc_html( $source_label ) . '</option>';
	}

	echo '</select>';

	echo '<p class="description fair-settings-description" id="fair-avatar-source-description">' . esc_html( $args['desc'] ) . '</p>';
}

/**
 * Get the pre-defined field args.
 *
 * @return array
 */
function get_avatar_source_field_args() : array {

	$field_args = [
		'class'      => 'fair-settings-row fair-avatar-source-setting-row',
		'label'      => __( 'Avatar Source', 'fair' ),
		'label_for'  => 'fair-avatar-source',
		'field_id'   => 'fair-avatar-source',
		'field_name' => AVATAR_SRC_SETTING_KEY,
		'value'      => get_site_option( AVATAR_SRC_SETTING_KEY, 'fair' ),
		'desc'       => __( 'Avatars will be loaded from the selected source.', 'fair' ),
	];

	return apply_filters( 'fair_avatar_source_field_args', $field_args );
}

/**
 * Get the available avatar sources.
 *
 * @return array
 */
function get_avatar_sources() : array {
	$default_sources = [
		'fair'     => __( 'FAIR Avatars', 'fair' ),
		'gravatar' => __( 'Gravatar', 'fair' ),
	];

	return apply_filters( 'fair_avatar_sources', $default_sources );
}
