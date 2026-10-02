import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { afterEach, describe, expect, test } from 'vitest';

const script = fileURLToPath( new URL( './build-blueprint.js', import.meta.url ) );

const run = ( args ) =>
	execFileSync( process.execPath, [ script, ...args ], {
		encoding: 'utf8',
		// Capture stderr too, so expected failures do not print help text.
		stdio: [ 'ignore', 'pipe', 'pipe' ],
	} );

const directories = [];

afterEach( () => {
	for ( const directory of directories.splice( 0 ) ) {
		fs.rmSync( directory, { recursive: true, force: true } );
	}
} );

describe( 'build-blueprint.js', () => {
	test( 'adds an install step for the given ZIP', () => {
		const blueprint = JSON.parse(
			run( [ '--plugin-zip', 'fair-plugin.zip' ] ),
		);

		expect( blueprint.steps ).toHaveLength( 1 );
		expect( blueprint.steps[ 0 ] ).toEqual( {
			step: 'installPlugin',
			pluginData: { resource: 'url', url: 'fair-plugin.zip' },
			options: { activate: true },
		} );
	} );

	test( 'keeps the settings from the default blueprint', () => {
		const blueprint = JSON.parse(
			run( [ '--plugin-zip', 'fair-plugin.zip' ] ),
		);
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

	test( 'returns a Playground URL with --type url', () => {
		const url = run( [ '--plugin-zip', 'fair-plugin.zip', '--type', 'url' ] );
		const parsed = new URL( url );

		expect( parsed.origin + parsed.pathname ).toBe(
			'https://playground.wordpress.net/',
		);
		expect( parsed.searchParams.get( 'mode' ) ).toBe( 'seamless' );

		const blueprint = JSON.parse( decodeURIComponent( parsed.hash.slice( 1 ) ) );
		expect( blueprint.steps[ 0 ].pluginData.url ).toBe( 'fair-plugin.zip' );
		expect( blueprint.login ).toBe( true );
	} );

	test( 'accepts --type json explicitly', () => {
		expect( () =>
			run( [ '--plugin-zip', 'fair-plugin.zip', '--type', 'json' ] ),
		).not.toThrow();
	} );

	test( 'rejects an unknown type', () => {
		expect( () =>
			run( [ '--plugin-zip', 'fair-plugin.zip', '--type', 'xml' ] ),
		).toThrow();
	} );

	test( 'renders a Markdown link with --type markdown', () => {
		const link = run( [
			'--plugin-zip',
			'fair-plugin.zip',
			'--type',
			'markdown',
			'--link-text',
			'Launch FAIR Connect 1.5.0',
		] );
		const match = link.match( /^\[🧪 (.+)\]\((.+)\)$/u );

		expect( match ).not.toBeNull();
		expect( match[ 1 ] ).toBe( 'Launch FAIR Connect 1.5.0' );

		const parsed = new URL( match[ 2 ] );
		const blueprint = JSON.parse( decodeURIComponent( parsed.hash.slice( 1 ) ) );
		expect( blueprint.steps[ 0 ].pluginData.url ).toBe( 'fair-plugin.zip' );
	} );

	test( 'uses a default link text', () => {
		expect( run( [
			'--plugin-zip',
			'fair-plugin.zip',
			'--type',
			'markdown',
		] ) ).toContain( '[🧪 Try it on WordPress Playground](' );
	} );

	test( 'fails without a ZIP', () => {
		expect( () => run( [] ) ).toThrow();
	} );
} );
