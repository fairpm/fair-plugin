import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';

import { afterEach, describe, expect, test } from 'vitest';

import { copyTree } from './copy-tree.js';

const directories = [];

const makeTree = () => {
	const root = fs.mkdtempSync( path.join( os.tmpdir(), 'copy-tree-' ) );
	directories.push( root );
	fs.mkdirSync( path.join( root, 'inc/packages' ), { recursive: true } );
	fs.mkdirSync( path.join( root, 'vendor' ), { recursive: true } );
	fs.writeFileSync( path.join( root, 'plugin.php' ), '<?php' );
	fs.writeFileSync( path.join( root, 'inc/packages/admin.php' ), '<?php' );
	fs.writeFileSync( path.join( root, 'vendor/autoload.php' ), '<?php' );
	fs.chmodSync( path.join( root, 'plugin.php' ), 0o755 );
	return root;
};

afterEach( () => {
	for ( const directory of directories.splice( 0 ) ) {
		fs.rmSync( directory, { recursive: true, force: true } );
	}
} );

describe( 'copyTree', () => {
	test( 'copies a tree and reports the number of files', () => {
		const source = makeTree();
		const dest = path.join( source, 'build', 'fair-plugin' );

		const files = copyTree( source, dest );

		expect( files ).toBe( 4 );
		expect( fs.readFileSync( path.join( dest, 'plugin.php' ), 'utf8' ) ).toBe(
			'<?php'
		);
		expect( fs.existsSync( path.join( dest, 'inc/packages/admin.php' ) ) ).toBe(
			true
		);
		expect( fs.existsSync( path.join( dest, 'vendor/autoload.php' ) ) ).toBe(
			true
		);
	} );

	test( 'preserves file modes', () => {
		const source = makeTree();
		const dest = path.join( source, 'build', 'fair-plugin' );

		copyTree( source, dest );

		const mode = fs.statSync( path.join( dest, 'plugin.php' ) ).mode & 0o777;
		expect( mode ).toBe( 0o755 );
	} );

	test( 'skips excluded paths', () => {
		const source = makeTree();
		const dest = path.join( source, 'build', 'fair-plugin' );

		const files = copyTree( source, dest, {
			exclude: ( rel ) => rel === 'vendor' || rel.startsWith( 'inc/' ),
		} );

		expect( files ).toBe( 1 );
		expect( fs.existsSync( path.join( dest, 'vendor' ) ) ).toBe( false );
		expect( fs.existsSync( path.join( dest, 'inc' ) ) ).toBe( false );
	} );

	test( 'reports nested exclusions relative to the source', () => {
		const source = makeTree();
		const dest = path.join( source, 'build', 'fair-plugin' );

		const files = copyTree( source, dest, {
			exclude: ( rel ) => rel === 'inc/packages/admin.php',
		} );

		expect( files ).toBe( 3 );
		expect( fs.existsSync( path.join( dest, 'inc/packages/admin.php' ) ) ).toBe(
			false
		);
	} );
} );