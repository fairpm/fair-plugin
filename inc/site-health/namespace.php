<?php
/**
 * Implements the plugin settings page.
 *
 * @package FAIR
 */

namespace FAIR\Site_Health;

/**
 * Bootstrap.
 */
function bootstrap() {
	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_media_scripts' );
}

/**
 * Enqueue scripts for Site Health.
 *
 * @param string $hook_suffix Hook to identify current screen.
 */
function enqueue_media_scripts( $hook_suffix ) {

	if ( 'site-health.php' !== $hook_suffix ) {
		return;
	}

	wp_enqueue_script( 'fair-site-health', esc_url( plugin_dir_url( \FAIR\PLUGIN_FILE ) . 'assets/js/fair-site-health.js' ), [ 'wp-i18n' ], \FAIR\VERSION, true );
	wp_localize_script( 'fair-site-health', 'fairSiteHealth',
		[
			'defaultRepoDomain' => \FAIR\Default_Repo\get_default_repo_domain(),
			'repoIPAddress'     => gethostbyname( \FAIR\Default_Repo\get_default_repo_domain() ),
			'errorMessageRegex' => build_error_message_regex(),
		]
	);

}

/**
 * Set up regular expression used for handling error messages.
 *
 * @return string
 */
function build_error_message_regex() {
	$regex = str_replace(
		[ '%1\$s', '%2\$s' ],
		[ '(?:.*?)', '(.*)' ],
		preg_quote( __( 'Your site is unable to reach WordPress.org at %1$s, and returned the error: %2$s' ) ) // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
	);
	$regex = $regex . '<\/p>';

	return $regex;
}

/**
 * Filter debug information.
 *
 * @param array $info {
 *     The debug information to be added to the core information page.
 *
 *     This is an associative multi-dimensional array, up to three levels deep.
 *     The topmost array holds the sections, keyed by section ID.
 *
 *     @type array ...$0 {
 *         Each section has a `$fields` associative array (see below), and each `$value` in `$fields`
 *         can be another associative array of name/value pairs when there is more structured data
 *         to display.
 *
 *         @type string $label       Required. The title for this section of the debug output.
 *         @type string $description Optional. A description for your information section which
 *                                   may contain basic HTML markup, inline tags only as it is
 *                                   outputted in a paragraph.
 *         @type bool   $show_count  Optional. If set to `true`, the amount of fields will be included
 *                                   in the title for this section. Default false.
 *         @type bool   $private     Optional. If set to `true`, the section and all associated fields
 *                                   will be excluded from the copied data. Default false.
 *         @type array  $fields {
 *             Required. An associative array containing the fields to be displayed in the section,
 *             keyed by field ID.
 *
 *             @type array ...$0 {
 *                 An associative array containing the data to be displayed for the field.
 *
 *                 @type string $label    Required. The label for this piece of information.
 *                 @type mixed  $value    Required. The output that is displayed for this field.
 *                                        Text should be translated. Can be an associative array
 *                                        that is displayed as name/value pairs.
 *                                        Accepted types: `string|int|float|(string|int|float)[]`.
 *                 @type string $debug    Optional. The output that is used for this field when
 *                                        the user copies the data. It should be more concise and
 *                                        not translated. If not set, the content of `$value`
 *                                        is used. Note that the array keys are used as labels
 *                                        for the copied data.
 *                 @type bool   $private  Optional. If set to `true`, the field will be excluded
 *                                        from the copied data, allowing you to show, for example,
 *                                        API keys here. Default false.
 *             }
 *         }
 *     }
 * }
 */
function filter_debug_information( $info ) {
	// translators: enabled default repository domain.
	$info['wp-core']['fields']['dotorg_communication']['label'] = sprintf( __( 'Communication with %s', 'fair' ), \FAIR\Default_Repo\get_default_repo_domain() );
	// translators: enabled default repository domain.
	$info['wp-core']['fields']['dotorg_communication']['value'] = sprintf( __( '%s is reachable', 'fair' ), \FAIR\Default_Repo\get_default_repo_domain() );

	return $info;
}
add_filter( 'debug_information', __NAMESPACE__ . '\\filter_debug_information' );
