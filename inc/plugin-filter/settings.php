<?php
/**
 * Settings UI for plugin filtering.
 *
 * @package FAIR
 */

namespace FAIR\Plugin_Filter\Settings;

use FAIR\Plugin_Filter;
use FAIR\Plugin_Filter\API_Client;

const SETTINGS_KEY = 'fair_plugin_filter_settings';

/**
 * Bootstrap settings functionality.
 *
 * @return void
 */
function bootstrap() {
	add_action( 'admin_init', __NAMESPACE__ . '\register_settings' );
	add_action( 'admin_init', __NAMESPACE__ . '\handle_test_connection' );
	add_action( 'admin_notices', __NAMESPACE__ . '\show_admin_notices' );
}

/**
 * Register plugin filter settings.
 *
 * @return void
 */
function register_settings() {
	// Add settings section to general settings page.
	add_settings_section(
		'fair_plugin_filter',
		__( 'FAIR Plugin Filtering', 'fair' ),
		__NAMESPACE__ . '\render_section_description',
		'general'
	);

	// Register the settings.
	register_setting(
		'general',
		SETTINGS_KEY,
		[
			'type'              => 'array',
			'sanitize_callback' => __NAMESPACE__ . '\sanitize_settings',
			'default'           => [],
			'show_in_rest'      => false,
		]
	);

	// Add individual fields.
	add_settings_field(
		'fair_plugin_filter_enabled',
		__( 'Enable Plugin Filtering', 'fair' ),
		__NAMESPACE__ . '\render_enabled_field',
		'general',
		'fair_plugin_filter'
	);

	add_settings_field(
		'fair_plugin_filter_api_endpoint',
		__( 'API Endpoint URL', 'fair' ),
		__NAMESPACE__ . '\render_api_endpoint_field',
		'general',
		'fair_plugin_filter'
	);

	add_settings_field(
		'fair_plugin_filter_risk_threshold',
		__( 'Risk Score Threshold', 'fair' ),
		__NAMESPACE__ . '\render_risk_threshold_field',
		'general',
		'fair_plugin_filter'
	);

	add_settings_field(
		'fair_plugin_filter_cache_duration',
		__( 'Cache Duration', 'fair' ),
		__NAMESPACE__ . '\render_cache_duration_field',
		'general',
		'fair_plugin_filter'
	);

	add_settings_field(
		'fair_plugin_filter_block_list_mode',
		__( 'Block List Mode', 'fair' ),
		__NAMESPACE__ . '\render_block_list_mode_field',
		'general',
		'fair_plugin_filter'
	);

	add_settings_field(
		'fair_plugin_filter_test',
		__( 'Test API Connection', 'fair' ),
		__NAMESPACE__ . '\render_test_connection_field',
		'general',
		'fair_plugin_filter'
	);
}

/**
 * Render section description.
 *
 * @return void
 */
function render_section_description() : void {
	echo '<p>';
	esc_html_e( 'Configure filtering of plugins based on external API labels and risk scores.', 'fair' );
	echo '</p>';
}

/**
 * Render enabled checkbox field.
 *
 * @return void
 */
function render_enabled_field() : void {
	$settings = Plugin_Filter\get_filter_settings();
	$checked = ! empty( $settings['enabled'] );
	?>
	<label>
		<input
			type="checkbox"
			name="<?php echo esc_attr( SETTINGS_KEY ); ?>[enabled]"
			value="1"
			<?php checked( $checked ); ?>
		/>
		<?php esc_html_e( 'Enable plugin filtering', 'fair' ); ?>
	</label>
	<p class="description">
		<?php esc_html_e( 'When enabled, plugins will be filtered based on the external API response.', 'fair' ); ?>
	</p>
	<?php
}

/**
 * Render API endpoint field.
 *
 * @return void
 */
function render_api_endpoint_field() : void {
	$settings = Plugin_Filter\get_filter_settings();
	$value = $settings['api_endpoint'] ?? '';
	?>
	<input
		type="url"
		name="<?php echo esc_attr( SETTINGS_KEY ); ?>[api_endpoint]"
		value="<?php echo esc_attr( $value ); ?>"
		class="regular-text"
		placeholder="https://api.example.com/plugin-labels"
	/>
	<p class="description">
		<?php esc_html_e( 'URL of the external API that provides plugin labels and risk scores.', 'fair' ); ?>
	</p>
	<?php
}

/**
 * Render risk threshold field.
 *
 * @return void
 */
function render_risk_threshold_field() : void {
	$settings = Plugin_Filter\get_filter_settings();
	$value = $settings['risk_threshold'] ?? 70;
	?>
	<input
		type="number"
		name="<?php echo esc_attr( SETTINGS_KEY ); ?>[risk_threshold]"
		value="<?php echo esc_attr( $value ); ?>"
		min="0"
		max="100"
		step="1"
		class="small-text"
	/>
	<p class="description">
		<?php esc_html_e( 'Plugins with a risk score above this threshold will be filtered out (0-100).', 'fair' ); ?>
	</p>
	<?php
}

/**
 * Render cache duration field.
 *
 * @return void
 */
function render_cache_duration_field() : void {
	$settings = Plugin_Filter\get_filter_settings();
	$value = $settings['cache_duration'] ?? 12 * HOUR_IN_SECONDS;

	$options = [
		HOUR_IN_SECONDS => __( '1 hour', 'fair' ),
		6 * HOUR_IN_SECONDS => __( '6 hours', 'fair' ),
		12 * HOUR_IN_SECONDS => __( '12 hours', 'fair' ),
		DAY_IN_SECONDS => __( '24 hours', 'fair' ),
	];
	?>
	<select name="<?php echo esc_attr( SETTINGS_KEY ); ?>[cache_duration]">
		<?php foreach ( $options as $duration => $label ) : ?>
			<option value="<?php echo esc_attr( $duration ); ?>" <?php selected( $value, $duration ); ?>>
				<?php echo esc_html( $label ); ?>
			</option>
		<?php endforeach; ?>
	</select>
	<p class="description">
		<?php esc_html_e( 'How long to cache API responses before fetching fresh data.', 'fair' ); ?>
	</p>
	<?php
}

/**
 * Render block list mode field.
 *
 * @return void
 */
function render_block_list_mode_field() : void {
	$settings = Plugin_Filter\get_filter_settings();
	$value = $settings['block_list_mode'] ?? 'strict';

	$options = [
		'strict' => __( 'Strict - Hide all blocked plugins regardless of risk score', 'fair' ),
		'lenient' => __( 'Lenient - Combine block list with risk score threshold', 'fair' ),
	];
	?>
	<?php foreach ( $options as $mode => $label ) : ?>
		<label style="display: block; margin-bottom: 8px;">
			<input
				type="radio"
				name="<?php echo esc_attr( SETTINGS_KEY ); ?>[block_list_mode]"
				value="<?php echo esc_attr( $mode ); ?>"
				<?php checked( $value, $mode ); ?>
			/>
			<?php echo esc_html( $label ); ?>
		</label>
	<?php endforeach; ?>
	<p class="description">
		<?php esc_html_e( 'How to handle plugins on the block list.', 'fair' ); ?>
	</p>
	<?php
}

/**
 * Render test connection field.
 *
 * @return void
 */
function render_test_connection_field() : void {
	?>
	<button
		type="submit"
		name="fair_plugin_filter_test_connection"
		value="1"
		class="button button-secondary"
	>
		<?php esc_html_e( 'Test API Connection', 'fair' ); ?>
	</button>
	<p class="description">
		<?php esc_html_e( 'Test the connection to the external API endpoint.', 'fair' ); ?>
	</p>
	<?php
}

/**
 * Handle test connection request.
 *
 * @return void
 */
function handle_test_connection() : void {
	if ( empty( $_POST['fair_plugin_filter_test_connection'] ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	check_admin_referer( 'general-options' );

	$api_client = new API_Client();
	$result = $api_client->test_connection();

	if ( is_wp_error( $result ) ) {
		set_transient(
			'fair_plugin_filter_test_error',
			$result->get_error_message(),
			30
		);
	} else {
		set_transient(
			'fair_plugin_filter_test_success',
			__( 'API connection test successful!', 'fair' ),
			30
		);
	}
}

/**
 * Show admin notices.
 *
 * @return void
 */
function show_admin_notices() : void {
	$screen = get_current_screen();
	if ( ! $screen || $screen->id !== 'options-general' ) {
		return;
	}

	$error = get_transient( 'fair_plugin_filter_test_error' );
	if ( $error ) {
		?>
		<div class="notice notice-error is-dismissible">
			<p>
				<strong><?php esc_html_e( 'Plugin Filter API Test Failed:', 'fair' ); ?></strong>
				<?php echo esc_html( $error ); ?>
			</p>
		</div>
		<?php
		delete_transient( 'fair_plugin_filter_test_error' );
	}

	$success = get_transient( 'fair_plugin_filter_test_success' );
	if ( $success ) {
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html( $success ); ?></p>
		</div>
		<?php
		delete_transient( 'fair_plugin_filter_test_success' );
	}
}

/**
 * Sanitize settings.
 *
 * @param array $input Input settings.
 * @return array Sanitized settings.
 */
function sanitize_settings( array $input ) : array {
	$sanitized = [];

	// Enabled checkbox.
	$sanitized['enabled'] = ! empty( $input['enabled'] );

	// API endpoint.
	if ( ! empty( $input['api_endpoint'] ) ) {
		$sanitized['api_endpoint'] = esc_url_raw( $input['api_endpoint'] );
	} else {
		$sanitized['api_endpoint'] = '';
	}

	// Risk threshold.
	if ( isset( $input['risk_threshold'] ) ) {
		$sanitized['risk_threshold'] = max( 0, min( 100, intval( $input['risk_threshold'] ) ) );
	} else {
		$sanitized['risk_threshold'] = 70;
	}

	// Cache duration.
	if ( ! empty( $input['cache_duration'] ) ) {
		$allowed_durations = [ HOUR_IN_SECONDS, 6 * HOUR_IN_SECONDS, 12 * HOUR_IN_SECONDS, DAY_IN_SECONDS ];
		$sanitized['cache_duration'] = in_array( intval( $input['cache_duration'] ), $allowed_durations, true )
			? intval( $input['cache_duration'] )
			: 12 * HOUR_IN_SECONDS;
	} else {
		$sanitized['cache_duration'] = 12 * HOUR_IN_SECONDS;
	}

	// Block list mode.
	if ( ! empty( $input['block_list_mode'] ) ) {
		$sanitized['block_list_mode'] = in_array( $input['block_list_mode'], [ 'strict', 'lenient' ], true )
			? $input['block_list_mode']
			: 'strict';
	} else {
		$sanitized['block_list_mode'] = 'strict';
	}

	// Clear cache when settings change.
	$api_client = new API_Client();
	$api_client->clear_cache();

	return $sanitized;
}
