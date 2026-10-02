import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';

import { afterEach, describe, expect, test } from 'vitest';

import { createExclusion, loadDistignore, parseDistignore } from './distignore.js';

const isExcluded = ( patterns, buildDir = '' ) =>
	createExclusion( { root: '/repo', buildDir, patterns } );

describe( 'parseDistignore', () => {
	test( 'ignores comments and blank lines', () => {
		expect( parseDistignore( '# a comment\n\n   \nnode_modules\n' ) ).toHaveLength(
			1,
		);
	} );

	test( 'matches a path and everything below it', () => {
		const patterns = parseDistignore( 'bin/\ntests\n' );
		const excluded = ( rel ) => patterns.some( ( matches ) => matches( rel ) );

		expect( excluded( 'bin' ) ).toBe( true );
		expect( excluded( 'bin/lib/copy-tree.js' ) ).toBe( true );
		expect( excluded( 'tests' ) ).toBe( true );
		expect( excluded( 'tests/phpunit/cache' ) ).toBe( true );
		expect( excluded( 'binary' ) ).toBe( false );
		expect( excluded( 'inc/packages' ) ).toBe( false );
	} );

	test( 'matches wildcards', () => {
		const patterns = parseDistignore( '*.swp\ncache/**\n' );
		const excluded = ( rel ) => patterns.some( ( matches ) => matches( rel ) );

		expect( excluded( '.plugin.swp' ) ).toBe( true );
		expect( excluded( 'cache/wp-env/index' ) ).toBe( true );
		expect( excluded( 'inc/cache' ) ).toBe( false );
		expect( excluded( 'plugin.swp.txt' ) ).toBe( false );
	} );
} );

describe( 'createExclusion', () => {
	test( 'excludes the build directory, so it cannot copy itself', () => {
		const excluded = isExcluded( [], '/repo/build' );

		expect( excluded( 'build' ) ).toBe( true );
		expect( excluded( 'build/fair-plugin/plugin.php' ) ).toBe( true );
		expect( excluded( 'bin' ) ).toBe( false );
	} );

	test( 'ignores a build directory outside the repository', () => {
		const excluded = isExcluded( [], '/tmp/build' );

		expect( excluded( 'build' ) ).toBe( false );
	} );
} );

describe( 'loadDistignore', () => {
	const directories = [];

	afterEach( () => {
		for ( const directory of directories.splice( 0 ) ) {
			fs.rmSync( directory, { recursive: true, force: true } );
		}
	} );

	test( 'reads patterns from a file', () => {
		const directory = fs.mkdtempSync( path.join( os.tmpdir(), 'distignore-' ) );
		directories.push( directory );
		fs.writeFileSync( path.join( directory, '.distignore' ), 'bin/\n*.swp\n' );

		const patterns = loadDistignore( path.join( directory, '.distignore' ) );
		const excluded = ( rel ) => patterns.some( ( matches ) => matches( rel ) );

		expect( excluded( 'bin/lib' ) ).toBe( true );
		expect( excluded( 'file.swp' ) ).toBe( true );
		expect( excluded( 'plugin.php' ) ).toBe( false );
	} );
} );
