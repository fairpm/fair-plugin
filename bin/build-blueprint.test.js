import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { afterEach, describe, expect, test } from 'vitest';

const script = fileURLToPath( new URL( './build-blueprint.js', import.meta.url ) );

const run = ( args ) =>
	JSON.parse(
		execFileSync( process.execPath, [ script, ...args ], {
			encoding: 'utf8',
			// Capture stderr too, so expected failures do not print help text.
			stdio: [ 'ignore', 'pipe', 'pipe' ],
		} ),
	);

const directories = [];

afterEach( () => {
	for ( const directory of directories.splice( 0 ) ) {
		fs.rmSync( directory, { recursive: true, force: true } );
	}
} );

describe( 'build-blueprint.js', () => {
	test( 'adds an install step for the given ZIP', () => {
		const blueprint = run( [ '--plugin-zip', 'fair-plugin.zip' ] );

		expect( blueprint.steps ).toHaveLength( 1 );
		expect( blueprint.steps[ 0 ] ).toEqual( {
			step: 'installPlugin',
			pluginData: { resource: 'url', url: 'fair-plugin.zip' },
			options: { activate: true },
		} );
	} );

	test( 'keeps the settings from the default blueprint', () => {
		const blueprint = run( [ '--plugin-zip', 'fair-plugin.zip' ] );
		const template = JSON.parse(
			fs.readFileSync(
				fileURLToPath(
					new URL( '../assets/blueprints/blueprint.json', import.meta.url ),
				),
				'utf8',
			),
		);

		expect( blueprint.login ).toBe( true );
		expect( blueprint.features.networking ).toBe( true );
		expect( blueprint.preferredVersions ).toEqual(
			template.preferredVersions,
		);
		expect( blueprint.landingPage ).toBe( template.landingPage );
	} );

	test( 'writes to a file when asked', () => {
		const directory = fs.mkdtempSync( path.join( os.tmpdir(), 'blueprint-' ) );
		directories.push( directory );
		const output = path.join( directory, 'blueprint.json' );

		execFileSync(
			process.execPath,
			[ script, '--plugin-zip', 'fair-plugin.zip', '-o', output ],
			{ encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'pipe' ] },
		);

		const blueprint = JSON.parse( fs.readFileSync( output, 'utf8' ) );
		expect( blueprint.steps[ 0 ].pluginData.url ).toBe( 'fair-plugin.zip' );
	} );

	test( 'fails without a ZIP', () => {
		expect( () => run( [] ) ).toThrow();
	} );
} );
