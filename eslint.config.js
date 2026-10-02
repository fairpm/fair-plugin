/**
 * ESLint configuration.
 *
 * Uses the WordPress coding standards for JavaScript, via the
 * @wordpress/eslint-plugin config, with two adjustments for the Node.js scripts
 * in bin/:
 *
 * - Prettier's formatting rule is off: these files are formatted by hand, in
 *   the same style as the PHP in this repository (tabs, double quotes, spaces
 *   inside parentheses), rather than by Prettier.
 * - console output is allowed: these are command line scripts.
 *
 * See CONTRIBUTING.md for why we do not use @wordpress/scripts.
 */

import wpPlugin from "@wordpress/eslint-plugin";

export default [
	{
		ignores: [ "**/build/**", "**/cache/**", "**/node_modules/**", "**/vendor/**" ],
	},
	...wpPlugin.configs.recommended,
	{
		files: [ "bin/**/*.{js,mjs,cjs}" ],
		languageOptions: {
			ecmaVersion: "latest",
			sourceType: "module",
			globals: {
				console: "readonly",
				process: "readonly",
			},
		},
		rules: {
			"no-console": "off",
			"prettier/prettier": "off",
		},
	},
];
