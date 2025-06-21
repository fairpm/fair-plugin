<?php

namespace FAIR\Icons;

$color = sanitize_hex_color( '#' . $_GET['color'] );

function sanitize_hex_color( $color ) {
	if ( '' === $color ) {
		return '';
	}

	// 3 or 6 hex digits, or the empty string.
	if ( preg_match( '|^#([A-Fa-f0-9]{3}){1,2}$|', $color ) ) {
		return $color;
	}
}

// Add the proper header
header( 'Content-Type: image/svg+xml' );

// Echo the SVG content
echo '<?xml version="1.0" encoding="utf-8"?>
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="100" height="100">
	<rect x="0" y="0" width="128" height="128" style="fill: ' . $color . '"/>
</svg>';
