#!/usr/bin/env node

// PHP versions and their supported WP versions.
const supported = {
	"8.0": [ "6.2", "6.3", "6.4", "6.5", "6.6", "6.7", "6.8", "6.9" ],
	"8.1": [ "6.2", "6.3", "6.4", "6.5", "6.6", "6.7", "6.8", "6.9" ],
	"8.2": [ "6.2", "6.3", "6.4", "6.5", "6.6", "6.7", "6.8", "6.9" ],
	"8.3": [ "6.4", "6.5", "6.6", "6.7", "6.8", "6.9" ],
	"8.4": [ "6.7", "6.8", "6.9" ],
	"8.5": [ "6.9" ],
};

const include = Object.entries( supported ).flatMap( ( [ php, wps ] ) =>
	wps.map( ( wp ) => ( { "php-version": php, "wp-version": wp } ) )
);

process.stdout.write( JSON.stringify( { include } ) );


