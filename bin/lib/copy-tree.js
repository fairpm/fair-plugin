// Copy a directory tree, skipping the paths an exclude predicate rejects.

import fs from 'node:fs';
import path from 'node:path';

/**
 * Copy a directory tree, preserving file modes.
 *
 * @param {string}                   sourceDir         Directory to copy from.
 * @param {string}                   destDir           Directory to copy into.
 * @param {Object}                   [options]
 * @param {(rel: string) => boolean} [options.exclude] Predicate taking a path relative to sourceDir.
 * @return {number} Number of files copied.
 */
export const copyTree = ( sourceDir, destDir, { exclude = () => false } = {} ) => {
	let files = 0;
	fs.mkdirSync( destDir, { recursive: true } );
	for ( const entry of fs.readdirSync( sourceDir, { withFileTypes: true } ) ) {
		const source = path.join( sourceDir, entry.name );
		// Always use forward slashes, to match how .distignore patterns are written.
		const rel = path.relative( sourceDir, source ).split( path.sep ).join( '/' );
		if ( exclude( rel ) ) {
			continue;
		}
		const dest = path.join( destDir, entry.name );
		if ( entry.isDirectory() ) {
			files += copyTree( source, dest, {
				exclude: ( child ) => exclude( `${ rel }/${ child }` ),
			} );
			continue;
		}
		fs.copyFileSync( source, dest );
		fs.chmodSync( dest, fs.statSync( source ).mode );
		files++;
	}
	return files;
};
