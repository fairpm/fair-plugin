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
/* echo '<?xml version="1.0" encoding="utf-8"?>
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="100" height="100">
	<rect x="0" y="0" width="128" height="128" style="fill: ' . $color . '"/>
</svg>'; */

// From https://heropatterns.com.
echo '<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<svg width="84px" height="48px" viewBox="0 0 84 48" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
    <!-- Generator: Sketch 40 (33762) - http://www.bohemiancoding.com/sketch -->
    <title>signal</title>
    <desc>Created with Sketch.</desc>
    <defs></defs>
    <g id="Page-1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
        <g id="signal" fill="' . $color . '">
            <path d="M0,0 L12,0 L12,6 L0,6 L0,0 Z M28,8 L40,8 L40,14 L28,14 L28,8 Z M42,0 L54,0 L54,6 L42,6 L42,0 Z M56,0 L68,0 L68,6 L56,6 L56,0 Z M56,8 L68,8 L68,14 L56,14 L56,8 Z M42,8 L54,8 L54,14 L42,14 L42,8 Z M42,24 L54,24 L54,30 L42,30 L42,24 Z M56,16 L68,16 L68,22 L56,22 L56,16 Z M70,16 L82,16 L82,22 L70,22 L70,16 Z M70,0 L82,0 L82,6 L70,6 L70,0 Z M28,32 L40,32 L40,38 L28,38 L28,32 Z M14,16 L26,16 L26,22 L14,22 L14,16 Z M0,24 L12,24 L12,30 L0,30 L0,24 Z M0,32 L12,32 L12,38 L0,38 L0,32 Z M14,32 L26,32 L26,38 L14,38 L14,32 Z M28,40 L40,40 L40,46 L28,46 L28,40 Z M14,40 L26,40 L26,46 L14,46 L14,40 Z M42,40 L54,40 L54,46 L42,46 L42,40 Z M56,32 L68,32 L68,38 L56,38 L56,32 Z M56,24 L68,24 L68,30 L56,30 L56,24 Z M70,32 L82,32 L82,38 L70,38 L70,32 Z M70,40 L82,40 L82,46 L70,46 L70,40 Z M14,24 L26,24 L26,30 L14,30 L14,24 Z M28,16 L40,16 L40,22 L28,22 L28,16 Z M14,8 L26,8 L26,14 L14,14 L14,8 Z M0,8 L12,8 L12,14 L0,14 L0,8 Z" id="Combined-Shape"></path>
        </g>
    </g>
</svg>';
