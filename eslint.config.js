/*
 * ESLint configuration.
 *
 * Uses the WordPress coding standards for JavaScript, via the config shipped
 * with the `@wordpress/eslint-plugin` package.
 *
 * We use its "esnext" config rather than "recommended": recommended also turns
 * on the Prettier formatting rule, and this repository does not run a
 * JavaScript formatter, so the formatting comes from the WordPress ESLint
 * config instead.
 *
 * Which files are linted is decided by the arguments to the lint script, not
 * here: see `lint:js` in package.json. Those files are the Node.js scripts in
 * this repository, so they get Node's globals, are allowed to write to the
 * console, and are tested with Vitest.
 */

import globals from 'globals';
import wordpress from '@wordpress/eslint-plugin';

export default [
	...wordpress.configs.esnext,
	// Vitest rules, as in WordPress core and Gutenberg.
	...wordpress.configs[ 'test-unit' ],
	{
		languageOptions: {
			sourceType: 'module',
			globals: globals.node,
		},
		rules: {
			'no-console': 'off',
		},
	},
];
