<?php
/**
 * Creates local icon SVG.
 *
 * @package FAIR
 */

// phpcs:ignore HM.Security.ValidatedSanitizedInput.MissingUnslash, HM.Security.ValidatedSanitizedInput.InputNotSanitized
$color = isset( $_GET['color'] ) ? sanitize_hex_color( '#' . stripslashes( $_GET['color'] ) ) : '';

/**
 * Sanitize hex color, same function in WP Core.
 *
 * @param  string $color Hex color.
 *
 * @return string
 */
function sanitize_hex_color( $color ) {
	if ( '' === $color ) {
		return '';
	}

	// 3 or 6 hex digits, or the empty string.
	if ( preg_match( '|^#([A-Fa-f0-9]{3}){1,2}$|', $color ) ) {
		return $color;
	}
}

// Add the proper header.
header( 'Content-Type: image/svg+xml' );

// Echo the SVG content.
// phpcs:disable HM.Security.EscapeOutput.OutputNotEscaped
echo '<?xml version="1.0" encoding="UTF-8"?>
<svg id="a" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200">
	<rect width="200" height="200" style="fill:' . $color . ';"/>
	<polygon points="0 0 0 43.3 25 0 0 0" style="fill:rgba(255,255,255,.43);"/><polygon points="75 0 25 0 50 43.3 75 0" style="fill:rgba(255,255,255,.43); opacity:.75;"/>
	<polygon points="59.3 200 90.7 200 75 173 59.3 200" style="fill:rgba(255,255,255,.43); opacity:.75;"/><polygon points="109.3 200 140.7 200 125 173 109.3 200" style="fill:rgba(255,255,255,.43); opacity:.22;"/>
	<polygon points="159.3 200 200 200 200 173 175 173 159.3 200" style="fill:rgba(255,255,255,.43); opacity:.17;"/><polygon points="0 173 0 200 40.7 200 25 173 0 173" style="fill:rgba(255,255,255,.43); opacity:.85;"/>
	<polygon points="0 130 0 173 25 130 0 130" style="fill:rgba(255,255,255,.43); opacity:.08;"/><polygon points="0 43.3 0 86.6 25 43.3 0 43.3" style="fill:rgba(255,255,255,.43); opacity:.25;"/>
	<polygon points="0 86.6 0 130 25 86.6 0 86.6" style="fill:rgba(255,255,255,.43); opacity:.36;"/><polygon points="175 0 125 0 150 43.3 175 0" style="fill:rgba(255,255,255,.43); opacity:.81;"/>
	<polygon points="200 86.6 200 43.3 175 43.3 200 86.6" style="fill:rgba(255,255,255,.43); opacity:.95;"/><polygon points="200 130 200 86.6 175 86.6 200 130" style="fill:rgba(255,255,255,.43); opacity:.32;"/>
	<polygon points="125 0 75 0 100 43.3 125 0" style="fill:rgba(255,255,255,.43); opacity:.22;"/><polygon points="175 0 200 43.3 200 0 175 0" style="fill:rgba(255,255,255,.43); opacity:.69;"/>
	<polygon points="200 173 200 130 175 130 200 173" style="fill:rgba(255,255,255,.43); opacity:.64;"/><polygon points="50 43.3 25 0 0 43.3 25 43.3 50 43.3" style="fill:rgba(255,255,255,.17);"/>
	<polygon points="75 43.3 100 43.3 75 0 50 43.3 75 43.3" style="fill:rgba(255,255,255,.43);"/><polygon points="125 43.3 150 43.3 125 0 100 43.3 125 43.3" style="fill:rgba(255,255,255,.49);"/>
	<polygon points="150 43.3 175 43.3 200 43.3 175 0 150 43.3" style="fill:rgba(255,255,255,.36);"/><polygon points="50 43.3 25 43.3 50 86.6 75 43.3 50 43.3" style="fill:rgba(255,255,255,.39);"/>
	<polygon points="125 43.3 100 43.3 75 43.3 100 86.6 125 43.3" style="fill:rgba(255,255,255,.39);"/><polygon points="150 86.6 175 43.3 150 43.3 125 43.3 150 86.6" style="fill:rgba(255,255,255,.43); opacity:.58;"/>
	<polygon points="50 86.6 25 43.3 0 86.6 25 86.6 50 86.6" style="fill:rgba(255,255,255,.43); opacity:.69;"/><polygon points="100 86.6 75 43.3 50 86.6 75 86.6 100 86.6" style="fill:rgba(255,255,255,.15);"/>
	<polygon points="100 86.6 125 86.6 150 86.6 125 43.3 100 86.6" style="fill:rgba(255,255,255,.31);"/><polygon points="150 86.6 175 86.6 200 86.6 175 43.3 150 86.6" style="fill:rgba(255,255,255,.19);"/>
	<polygon points="50 86.6 25 86.6 50 130 75 86.6 50 86.6" style="fill:rgba(255,255,255,.43); opacity:.58;"/><polygon points="100 130 125 86.6 100 86.6 75 86.6 100 130" style="fill:rgba(255,255,255,.43); opacity:.95;"/>
	<polygon points="150 130 175 86.6 150 86.6 125 86.6 150 130" style="fill:rgba(255,255,255,.25);"/><polygon points="50 130 25 86.6 0 130 25 130 50 130" style="fill:rgba(255,255,255,.43); opacity:.95;"/>
	<polygon points="100 130 75 86.6 50 130 75 130 100 130" style="fill:rgba(255,255,255,.34);"/><polygon points="100 130 125 130 150 130 125 86.6 100 130" style="fill:rgba(255,255,255,.27);"/>
	<polygon points="150 130 175 130 200 130 175 86.6 150 130" style="fill:rgba(255,255,255,.38);"/><polygon points="50 130 25 130 50 173 75 130 50 130" style="fill:rgba(255,255,255,.27);"/>
	<polygon points="100 130 75 130 100 173 125 130 100 130" style="fill:rgba(255,255,255,.44);"/><polygon points="150 173 175 130 150 130 125 130 150 173" style="fill:rgba(255,255,255,.12);"/>
	<polygon points="50 173 25 130 0 173 25 173 50 173" style="fill:rgba(255,255,255,.16);"/><polygon points="50 173 75 173 100 173 75 130 50 173" style="fill:rgba(255,255,255,.35);"/>
	<polygon points="100 173 125 173 150 173 125 130 100 173" style="fill:rgba(255,255,255,.22);"/><polygon points="150 173 175 173 200 173 175 130 150 173" style="fill:rgba(255,255,255,.29);"/>
	<polygon points="50 173 25 173 40.7 200 59.3 200 75 173 50 173" style="fill:rgba(255,255,255,.41);"/><polygon points="100 173 75 173 90.7 200 109.3 200 125 173 100 173" style="fill:rgba(255,255,255,.25);"/>
	<polygon points="150 173 125 173 140.7 200 159.3 200 175 173 150 173" style="fill:rgba(255,255,255,.33);"/>
</svg>';
// phpcs:enable HM.Security.EscapeOutput.OutputNotEscaped
