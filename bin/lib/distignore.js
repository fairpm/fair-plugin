// Parse .distignore into matchers for repository-relative paths.
//
// Patterns are one per line; blank lines and lines starting with "#" are
// ignored. A pattern without wildcards matches that path and everything below
// it, so "bin/" and "bin" both exclude the whole directory.

import fs from 'node:fs';
import path from 'node:path';

const globToRegExp = ( pattern ) => {
	const source = pattern
		.replace( /[.+^${}()|[\]\\]/g, '\\$&' )
		.replace( /\*\*/g, '\u0000' )
		.replace( /\*/g, '[^/]*' )
		.replace( /\u0000/g, '.*' )
		.replace( /\?/g, '.' );
	return new RegExp( `^${ source }(/|$)` );
};

export const parseDistignore = ( contents ) =>
	contents
		.split( '\n' )
		.map( ( line ) => line.trim() )
		.filter( ( line ) => line && ! line.startsWith( '#' ) )
		.map( ( pattern ) => pattern.replace( /\/+$/, '' ) )
		.filter( Boolean )
		.map( ( pattern ) => {
			if ( ! pattern.includes( '*' ) ) {
				return ( rel ) => rel === pattern || rel.startsWith( `${ pattern }/` );
			}
			const regex = globToRegExp( pattern );
			return ( rel ) => regex.test( rel );
		} );

export const loadDistignore = ( file ) => parseDistignore( fs.readFileSync( file, 'utf8' ) );

/**
 * Build the predicate used to decide which repository-relative paths to copy.
 *
 * @param {Object}                          options
 * @param {string}                          options.root     Repository root.
 * @param {string}                          options.buildDir Build directory, excluded so it cannot copy itself.
 * @param {Array<(rel: string) => boolean>} options.patterns Matchers from parseDistignore().
 * @return {(rel: string) => boolean} Predicate taking a relative path.
 */
export const createExclusion = ( { root, buildDir, patterns } ) => {
	const buildRel = path.relative( root, buildDir ).split( path.sep ).join( '/' );
	const insideRepo = buildRel && ! buildRel.startsWith( '..' );
	return ( rel ) =>
		( insideRepo && ( rel === buildRel || rel.startsWith( `${ buildRel }/` ) ) ) ||
		patterns.some( ( matches ) => matches( rel ) );
};
