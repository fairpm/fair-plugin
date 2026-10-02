import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';

import { afterEach, describe, expect, test } from 'vitest';

import { copyTree } from './copy-tree.js';

const directories = [];

// Whether a path relative to the source is a directory or lives below it.
const under = ( rel, directory ) =>
	rel === directory || rel.startsWith( `${ directory }/` );

/**
 * Create a source tree and a separate destination directory.
 *
 * The destination deliberately lives outside the source: copying a tree into
 * itself would recurse forever, which bin/release.js avoids by excluding the
 * build directory with createExclusion().
 */
const makeTrees = () => {
	const source = fs.mkdtempSync( path.join( os.tmpdir(), 'copy-tree-src-' ) );
	const dest = fs.mkdtempSync( path.join( os.tmpdir(), 'copy-tree-dest-' ) );
	directories.push( source, dest );
	fs.mkdirSync( path.join( source, 'inc/packages' ), { recursive: true } );
	fs.mkdirSync( path.join( source, 'vendor' ), { recursive: true } );
	fs.writeFileSync( path.join( source, 'plugin.php' ), '<?php' );
	fs.writeFileSync( path.join( source, 'inc/packages/admin.php' ), '<?php' );
	fs.writeFileSync( path.join( source, 'vendor/autoload.php' ), '<?php' );
	fs.chmodSync( path.join( source, 'plugin.php' ), 0o755 );
	return { source, dest };
};

afterEach( () => {
	for ( const directory of directories.splice( 0 ) ) {
		fs.rmSync( directory, { recursive: true, force: true } );
	}
} );

describe( 'copyTree', () => {
	test( 'copies a tree and reports the number of files', () => {
		const { source, dest } = makeTrees();

		const files = copyTree( source, dest );

		expect( files ).toBe( 3 );
		expect( fs.readFileSync( path.join( dest, 'plugin.php' ), 'utf8' ) ).toBe(
			'<?php',
		);
		expect( fs.existsSync( path.join( dest, 'inc/packages/admin.php' ) ) ).toBe(
			true,
		);
		expect( fs.existsSync( path.join( dest, 'vendor/autoload.php' ) ) ).toBe(
			true,
		);
	} );

	test( 'preserves file modes', () => {
		const { source, dest } = makeTrees();

		copyTree( source, dest );

		const copied = path.join( dest, 'plugin.php' );
		expect( fs.statSync( copied ).mode.toString( 8 ).slice( -3 ) ).toBe( '755' );
		expect( () => fs.accessSync( copied, fs.constants.X_OK ) ).not.toThrow();
	} );

	test( 'skips excluded directories', () => {
		const { source, dest } = makeTrees();

		const files = copyTree( source, dest, {
			exclude: ( rel ) => under( rel, 'vendor' ) || under( rel, 'inc' ),
		} );

		expect( files ).toBe( 1 );
		expect( fs.existsSync( path.join( dest, 'vendor' ) ) ).toBe( false );
		expect( fs.existsSync( path.join( dest, 'inc' ) ) ).toBe( false );
	} );

	test( 'reports nested paths relative to the source', () => {
		const { source, dest } = makeTrees();

		const files = copyTree( source, dest, {
			exclude: ( rel ) => rel === 'inc/packages/admin.php',
		} );

		expect( files ).toBe( 2 );
		expect( fs.existsSync( path.join( dest, 'inc/packages/admin.php' ) ) ).toBe(
			false,
		);
	} );

	test( 'does not recurse into a destination inside the source when excluded', () => {
		const source = fs.mkdtempSync( path.join( os.tmpdir(), 'copy-tree-src-' ) );
		directories.push( source );
		fs.writeFileSync( path.join( source, 'plugin.php' ), '<?php' );

		// This is the shape bin/release.js uses: the build directory is inside
		// the repository, and excluded so it cannot copy itself.
		const files = copyTree( source, path.join( source, 'build/fair-plugin' ), {
			exclude: ( rel ) => under( rel, 'build' ),
		} );

		expect( files ).toBe( 1 );
		expect(
			fs.existsSync( path.join( source, 'build/fair-plugin/plugin.php' ) ),
		).toBe( true );
		expect(
			fs.existsSync( path.join( source, 'build/fair-plugin/build' ) ),
		).toBe( false );
	} );
} );
