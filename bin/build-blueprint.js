#!/usr/bin/env node

// Build a WordPress Playground blueprint from assets/blueprints/blueprint.json,
// with a step to install the plugin from the given ZIP path.
//
// Usage:
//   bin/build-blueprint.js --plugin-zip <url> [-o <output>]
//
// Writes the blueprint to stdout, or to <output> when given ("-" is stdout).

const fs = require( "node:fs" );
const path = require( "node:path" );

const usage = "Usage: bin/build-blueprint.js --plugin-zip <url> [-o <output>]";

// Options are parsed as --name value or --name=value, so the interface can grow
// without callers having to guess which argument is which.
const aliases = { o: "output" };
const args = process.argv.slice( 2 );
const options = {};
for ( let i = 0; i < args.length; i++ ) {
	let arg = args[ i ];
	if ( arg.startsWith( "-" ) && ! arg.startsWith( "--" ) ) {
		arg = `--${ aliases[ arg.slice( 1 ) ] ?? "" }`;
	}
	if ( ! arg.startsWith( "--" ) ) {
		console.error( `Unexpected argument: ${ args[ i ] }\n${ usage }` );
		process.exit( 1 );
	}
	const [ name, inline ] = arg.slice( 2 ).split( "=", 2 );
	if ( ! [ "plugin-zip", "output" ].includes( name ) ) {
		console.error( `Unknown option: --${ name }\n${ usage }` );
		process.exit( 1 );
	}
	if ( inline !== undefined ) {
		options[ name ] = inline;
		continue;
	}
	const value = args[ ++i ];
	if ( ! value ) {
		console.error( `Missing value for --${ name }\n${ usage }` );
		process.exit( 1 );
	}
	options[ name ] = value;
}

if ( ! options[ "plugin-zip" ] ) {
	console.error( usage );
	process.exit( 1 );
}

const blueprint = JSON.parse(
	fs.readFileSync( path.join( __dirname, "..", "assets/blueprints/blueprint.json" ), "utf8" )
);

blueprint.steps ??= [];
blueprint.steps.push( {
	step: "installPlugin",
	pluginData: {
		resource: "url",
		url: options[ "plugin-zip" ],
	},
	// The folder is guessed from the ZIP's top-level directory (fair-plugin/).
	options: {
		activate: true,
	},
} );

const json = `${ JSON.stringify( blueprint, null, "\t" ) }\n`;

if ( options.output && options.output !== "-" ) {
	fs.writeFileSync( options.output, json );
} else {
	process.stdout.write( json );
}