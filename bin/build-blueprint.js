#!/usr/bin/env node

// Build a WordPress Playground blueprint from assets/blueprints/blueprint.json,
// with a step to install the plugin from the given ZIP path.
//
// Writes the blueprint as JSON by default, as a ready to open Playground URL
// with --type url, or as a Markdown link with --type markdown. Either goes to
// stdout, or to the output file ("-" is stdout).

import fs from 'node:fs';
import { fileURLToPath } from 'node:url';

import { Command, Option } from 'commander';

const PLAYGROUND_URL = 'https://playground.wordpress.net/';

const playgroundUrl = ( blueprint ) => {
	const url = new URL( PLAYGROUND_URL );
	url.searchParams.set( 'mode', 'seamless' );
	url.hash = encodeURIComponent( JSON.stringify( blueprint ) );
	return `${ url }`;
};

// One entry per --type value.
const TYPES = {
	json: ( blueprint ) => `${ JSON.stringify( blueprint, null, '\t' ) }\n`,
	url: playgroundUrl,
	markdown: ( blueprint, options ) =>
		`[🧪 ${ options.linkText }](${ playgroundUrl( blueprint ) })`,
};

const blueprintFile = fileURLToPath(
	new URL( '../assets/blueprints/blueprint.json', import.meta.url ),
);

const program = new Command()
	.name( 'build-blueprint.js' )
	.description(
		'Build a WordPress Playground blueprint that installs the plugin from a ZIP.',
	)
	.requiredOption(
		'--plugin-zip <path>',
		'path or URL of the plugin ZIP to install',
	)
	.addOption(
		new Option( '--type <type>', 'how to format the output' )
			.choices( Object.keys( TYPES ) )
			.default( 'json' ),
	)
	.option( '-o, --output <path>', 'write the result here instead of stdout' )
	.option(
		'--link-text <text>',
		'link text to use with --type markdown',
		'Try it on WordPress Playground',
	)
	.allowExcessArguments( false )
	.showHelpAfterError();

program.parse();
const options = program.opts();

const blueprint = JSON.parse( fs.readFileSync( blueprintFile, 'utf8' ) );

blueprint.steps ??= [];
blueprint.steps.push( {
	step: 'installPlugin',
	pluginData: {
		resource: 'url',
		url: options.pluginZip,
	},
	// The folder is guessed from the ZIP's top-level directory (fair-plugin/).
	options: {
		activate: true,
	},
} );

// Types that render a link take the link text from --link-text.
const output = TYPES[ options.type ]( blueprint, options );

if ( options.output && options.output !== '-' ) {
	fs.writeFileSync( options.output, output );
} else {
	process.stdout.write( output );
}
