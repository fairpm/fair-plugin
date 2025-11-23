<?php
/**
 * Replaces calls to the WordPress.org Secret Key API with locally generated keys and salts.
 *
 * @package FAIR
 */

namespace FAIR\Salts;

/**
 * This is the character set used for various functions. Refrain from using \ (backslash) to prevent errors.
 */
const CHARACTER_SET = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_ []{}<>~`+=,.;:/?|';

/**
 * Bootstrap.
 */
function bootstrap() {
	add_filter( 'pre_http_request', __NAMESPACE__ . '\\replace_salt_generation_via_api', 10, 3 );
}

/**
 * Replace the call to retrieve generated salt values.
 *
 * @param bool|array $value Filtered value, or false to proceed.
 * @param array $args HTTP request arguments.
 * @param string $url The request URL.
 * @return bool|array Replaced value, or false to proceed.
 */
function replace_salt_generation_via_api( $value, $args, $url ) {
	if ( str_contains( $url, 'api.wordpress.org/secret-key/1.1/salt' ) ) {
		return get_salt_generation_response();
	}

	// Continue as we were.
	return $value;
}

/**
 * Generate the salts we need.
 *
 * @return array HTTP API response-like data.
 */
function get_salt_generation_response() {

	// Send back an API worthy response.
	return [
		'body'               => generate_salt_response_body(),
		'cookies'            => [],
		'headers'            => [],
		'filename'           => '',
		'http_response_code' => 200,
		'response'           => [
			'code'    => 200,
			'message' => 'OK',
		],
	];
}

/**
 * Generate the body for the API response.
 *
 * @return string
 */
function generate_salt_response_body() {

	// Grab my key names.
	$get_key_names = define_salt_keynames();

	$salt_defines = '';

	// Now loop my key names and add a salt to each one.
	foreach ( $get_key_names as $keyname ) {
		$salt_defines .= 'define( \'' . $keyname . '\', \'' . generate_salt_string() . '\' );' . "\n";
	}

	// Send back the string.
	return $salt_defines;
}

/**
 * Define and return the array of names.
 *
 * @return array
 */
function define_salt_keynames() {
	return [
		'AUTH_KEY',
		'SECURE_AUTH_KEY',
		'LOGGED_IN_KEY',
		'NONCE_KEY',
		'AUTH_SALT',
		'SECURE_AUTH_SALT',
		'LOGGED_IN_SALT',
		'NONCE_SALT',
	];
}

/**
 * Generate a unique string for the salt, using multiple crypto methods.
 *
 * @return string
 */
function generate_salt_string() {

	// Try the same secure CSPRNG method core uses first.
	if ( function_exists( 'random_int' ) ) {
		return generate_string_via_random_int();
	}

	// Leverage OpenSSL's pseudo.
	if ( function_exists( 'openssl_random_pseudo_bytes' ) ) {
		return generate_string_via_openssl_random();
	}

	// Use mt_rand which is OK but not ideal.
	if ( function_exists( 'mt_rand' ) ) {
		return generate_string_via_mt_rand();
	}

	// Shuffle is random, but this is not ideal.
	if ( function_exists( 'str_shuffle' ) ) {
		return generate_string_via_str_shuffle();
	}

	// Ok. Lowest level attempt, same as core.
	return generate_string_via_substr();
}

/**
 * Use the `random_int` function to create a random string.
 *
 * @return string A 64 character string.
 */
function generate_string_via_random_int() {

	// Set a max amount.
	$define_max = mb_strlen( CHARACTER_SET, '8bit' ) - 1;

	$saltgrain = '';

	// Loop through to generate each character of the string.
	for ( $i = 0; $i < 64; ++$i ) {
		$saltgrain .= CHARACTER_SET[ random_int( 0, $define_max ) ];
	}

	return esc_attr( $saltgrain );
}

/**
 * Use the `openssl_random_pseudo_bytes` function to create a random string.
 *
 * @return string A 64 character string.
 */
function generate_string_via_openssl_random() {

	// Generate some bytes to begin.
	$set_bytes = openssl_random_pseudo_bytes( 138 );

	// Now encode it to make sure it's a usable string.
	$saltshaker = base64_encode( $set_bytes );

	// Establish the first 64 characters.
	$saltgrain = substr( $saltshaker, 0, 64 );

	return esc_attr( $saltgrain );
}

/**
 * Use the `mt_rand` function to create a random string.
 *
 * @return string A 64 character string.
 */
function generate_string_via_mt_rand() {

	$saltgrain = '';

	// Loop through to generate each character of the string.
	for ( $i = 0; $i < 64; $i++ ) {

		// Randomly select an index from the character set using mt_rand().
		$set_index = wp_rand( 0, strlen( CHARACTER_SET ) - 1 );

		// Append the character to the string.
		$saltgrain .= CHARACTER_SET[ $set_index ];
	}

	return esc_attr( $saltgrain );
}

/**
 * Use the `str_shuffle` function to create a random string.
 *
 * @return string A 64 character string.
 */
function generate_string_via_str_shuffle() {

	// Shuffle the string to randomize the order of characters.
	$shuffle_characters = str_shuffle( CHARACTER_SET );

	// Establish a substring of the shuffled string with our length.
	$shuffled_saltgrain = substr( $shuffle_characters, 0, 64 );

	return esc_attr( $shuffled_saltgrain );
}

/**
 * Use the `substr` function to create a random string, which
 * is basically what `wp_generate_password` does.
 *
 * @return string A 64 character string.
 */
function generate_string_via_substr() {

	$saltgrain = '';

	// Loop through to generate each character of the string.
	for ( $i = 0; $i < 64; $i++ ) {

		// Append the character to the string.
		$saltgrain .= substr( CHARACTER_SET, wp_rand( 0, strlen( CHARACTER_SET ) - 1 ), 1 );
	}

	return esc_attr( $saltgrain );
}
