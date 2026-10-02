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
 * The scripts in `bin/` are Node.js command line tools, so they get Node's
 * globals and are allowed to write to the console. Anything added to `bin/`
 * in future is covered by the same override.
 *
 * See CONTRIBUTING.md for why we do not use `@wordpress/scripts`.
 */

import globals from 'globals';
import wordpress from '@wordpress/eslint-plugin';

export default [
	...wordpress.configs.esnext,
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
