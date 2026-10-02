#!/usr/bin/env node

// Build a distributable plugin ZIP.
//
// Stages the working tree into a build directory, applying the exclusions in
// .distignore, then ZIPs it so the archive contains a single top-level directory
// named after the plugin slug (required by WordPress and Playground).
//
// Run the build first (npm run build) so Composer dependencies and built assets
// are present in the working tree.

import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

import { Command } from 'commander';

import { copyTree } from './lib/copy-tree.js';
import { createExclusion, loadDistignore } from './lib/distignore.js';

const root = fileURLToPath( new URL( '..', import.meta.url ) );

const program = new Command()
	.name( 'release.js' )
	.description( 'Build a distributable plugin ZIP.' )
	.option( '-b, --build-dir <dir>', 'directory to stage the plugin in', 'build' )
	.option( '-z, --zip <path>', 'path of the ZIP file to write', 'build/fair-plugin.zip' )
	.option(
		'--slug <slug>',
		'top-level directory inside the ZIP',
		path.basename( root ),
	)
	.allowExcessArguments( false )
	.showHelpAfterError();

program.parse();
const options = program.opts();

const buildDir = path.resolve( root, options.buildDir );
const stageDir = path.join( buildDir, options.slug );
const zipPath = path.resolve( root, options.zip );

// Fail early: the build must have run.
if ( ! fs.existsSync( path.join( root, 'vendor/autoload.php' ) ) ) {
	console.error( 'vendor/autoload.php not found: run `npm run build` first' );
	process.exit( 1 );
}

const exclude = createExclusion( {
	root,
	buildDir,
	patterns: loadDistignore( path.join( root, '.distignore' ) ),
} );

fs.rmSync( buildDir, { recursive: true, force: true } );
const files = copyTree( root, stageDir, { exclude } );
console.log( `Staged ${ files } files in ${ path.relative( root, stageDir ) }` );

fs.mkdirSync( path.dirname( zipPath ), { recursive: true } );
const zip = spawnSync( 'zip', [ '-qr', zipPath, options.slug ], {
	cwd: buildDir,
	stdio: 'inherit',
} );
if ( zip.error ) {
	console.error( `Could not run zip: ${ zip.error.message }` );
	process.exit( 1 );
}
if ( zip.status !== 0 ) {
	console.error( `zip exited with status ${ zip.status }` );
	process.exit( zip.status ?? 1 );
}

console.log( `Created ${ path.relative( root, zipPath ) }` );

// Guard against shipping a broken archive: the plugin entry file and its
// Composer autoloader must be inside the ZIP.
const entries = spawnSync( 'unzip', [ '-Z1', zipPath ], { encoding: 'utf8' } ).stdout.split(
	'\n',
);
const missing = [ 'plugin.php', 'vendor/autoload.php' ]
	.map( ( entry ) => `${ options.slug }/${ entry }` )
	.filter( ( entry ) => ! entries.includes( entry ) );
if ( missing.length ) {
	console.error( `Missing from ${ path.relative( root, zipPath ) }: ${ missing.join( ', ' ) }` );
	process.exit( 1 );
}
