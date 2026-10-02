# Contributing

FAIR is an open project, and we welcome contributions from all.

FAIR is administered directly by the [Technical Steering Committee](https://github.com/fairpm/tsc). The FAIR Connect plugin is currently maintained by the Technical Independence Working Group, in conjunction with the FAIR Working Group; this maintenance will transition to the FAIR WG's responsibility once the independence work is complete.

All contributions must be made under the GNU General Public License v2, and are made available to users under the terms of the GPL v2 or later.

Contributors are required to sign-off their commits to agree to the terms of the [Developer Certificate of Origin (DCO)](https://developercertificate.org/). You can do this by adding the `-s` parameter to `git commit`:

```sh
$ git commit -s -m 'My commit message.'
```

**Please Note:** This is adding a _sign-off_ to the commit, which is not the same as *signing* your commits (which involves GPG keys).

## Development Environment

This plugin is ready to use with wp-env for local development, with a default configuration included in the repository. `npm run env` is an alias for `wp-env`:

- `npm install` to install wp-env and other dependencies.
- `npm run env start` to start the development server. Run `npm run env start -- --xdebug=coverage` to enable Xdebug with test coverage reporting.
- `npm run env logs` to get the logs.
- `npm run env stop` to stop the development server.
- `npm run cli` to run any CLI commands inside the environment, such as `npm run cli -- wp plugin list`.

By default `wp-env` is configured with PHP 8.0 (our minimum supported version), as well as Airplane Mode to avoid inadvertent requests.

For linting and static analysis:

- `npm run lint:php:phpcs` to run PHPCS (configured in [`phpcs.xml.dist`](phpcs.xml.dist)).
- `npm run lint:php:phpstan` to run PHPStan (configured in [`phpstan.dist.neon`](phpstan.dist.neon)).
- `npm run lint:js` to run ESLint over the Node.js scripts in [`bin/`](bin), via [`wp-scripts lint-js`](https://www.npmjs.com/package/@wordpress/scripts) (configured in [`eslint.config.js`](eslint.config.js)).
- `npm run format:php:phpcs` to automatically fix PHPCS issues.
- `npm run format:php:phpstan` to automatically fix PHPStan issues.
- `npm run cli -- composer phpstan-baseline` to update the PHPStan baseline [`tests/phpstan-baseline.neon`](tests/phpstan-baseline.neon) as you fix the reported issues.

JavaScript tooling comes from [`@wordpress/scripts`](https://www.npmjs.com/package/@wordpress/scripts), so linting and unit tests match WordPress core and Gutenberg. We keep our own [`eslint.config.js`](eslint.config.js) — `wp-scripts lint-js` uses the project's ESLint config when it finds one — so the rules, formatting (single quotes, tabs) and the Vitest rules come from [`@wordpress/eslint-plugin`](https://www.npmjs.com/package/@wordpress/eslint-plugin), with Node's globals and `console` output allowed for our command line scripts. There is no separate JavaScript code formatter in this repository; fix formatting with `npm run lint:js -- --fix`.

Development uses Node 22, pinned in [`.nvmrc`](.nvmrc): `nvm use` before running `npm install`. `@wordpress/scripts` also supports Node 24, but we do not need it yet.

For PHP unit tests:

- `npm run test:php` to run PHPUnit tests for WP single site (configured in [`phpunit.xml.dist`](phpunit.xml.dist)).
- `npm run test:php:multisite` to run PHPUnit multisite tests (configured in [`phpunit-multisite.xml.dist`](phpunit-multisite.xml.dist)).

For JavaScript unit tests:

- `npm run test:js` to run the unit tests for the Node.js scripts in [`bin/`](bin), via [`wp-scripts test-unit-js`](https://www.npmjs.com/package/@wordpress/scripts).
- `npm run test-watch:js` to run those tests in watch mode. The script is named `test-watch:js`, not `test:js:watch`, so that it is not picked up by `npm-run-all test:*`.

Test files live next to the code they cover, as `*.test.js`, and there is no Vitest configuration file: Vitest picks them up with its defaults. The runner is [Vitest](https://vitest.dev/), as in WordPress core and Gutenberg, which also runs our ES modules natively without a Babel transform.

To enable test coverage reporting, start the environment with `npm run env start -- --xdebug=coverage` and then:

- `npm run coverage:php:single` for coverage of single site tests.
- `npm run coverage:php:multisite` for coverage of multisite tests.
- `npm run coverage:php:full` for coverage of both tests.

### Configuring PHP and WP Versions

To run a specific version of PHP or WP with your local development environment, create a `.wp-env.override.json` file in the root of the repository with the following contents:

```json
{
	"phpVersion": "8.5",
	"core": "https://wordpress.org/wordpress-6.9.zip"
}
```

and restart the development environment with `npm run env start`. Alternatively, set the `WP_ENV_PHP_VERSION` and `WP_ENV_CORE` environment variables before starting the environment.
