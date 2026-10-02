#!/usr/bin/env node

// Build a WordPress Playground blueprint from assets/blueprints/blueprint.json,
// with a step to install the plugin from the given ZIP path.
//
// Writes the blueprint to stdout, or to the given output file ("-" is stdout).

import fs from 'node:fs';
import { fileURLToPath } from 'node:url';

import { Command } from 'commander';

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
	.option( '-o, --output <path>', 'write the blueprint here instead of stdout' )
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

const json = `${ JSON.stringify( blueprint, null, '\t' ) }\n`;

if ( options.output && options.output !== '-' ) {
	fs.writeFileSync( options.output, json );
} else {
	process.stdout.write( json );
}
