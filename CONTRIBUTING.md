# Contributing

FAIR is an open project, and we welcome contributions from all.

FAIR is administered directly by the [Technical Steering Committee](https://github.com/fairpm/tsc). The FAIR Connect plugin is currently maintained by the Technical Independence Working Group, in conjunction with the FAIR Working Group; this maintenance will transition to the FAIR WG's responsibility once the independence work is complete.

All contributions must be made under the GNU General Public License v2, and are made available to users under the terms of the GPL v2 or later.

Contributors are required to sign-off their commits to agree to the terms of the [Developer Certificate of Origin (DCO)](https://developercertificate.org/). You can do this by adding the `-s` parameter to `git commit`:

```sh
$ git commit -s -m 'My commit message.'
```

**Please Note:** This is adding a _sign-off_ to the commit, which is not the same as *signing* your commits (which involves GPG keys).

## Testing Changes in WordPress Playground

Every pull request gets a comment with a link that opens the plugin in [WordPress Playground](https://playground.wordpress.net), running in the browser. The plugin is installed from a ZIP built for that pull request, Composer dependencies included, so the preview behaves like a real install rather than a source checkout.

Click the link, log in, and test your change against a real WordPress.

### How it works

Two GitHub Actions workflows cooperate, both built on [`WordPress/action-wp-playground-pr-preview`](https://github.com/WordPress/action-wp-playground-pr-preview):

1. [`.github/workflows/playground-pr.yml`](.github/workflows/playground-pr.yml) runs on `pull_request` with **read-only** permissions. It builds the ZIP with `npm run release` (see [`bin/release.js`](bin/release.js)) and a Blueprint with `npm run blueprint` (see [`bin/build-blueprint.js`](bin/build-blueprint.js)), then uploads both as a workflow artifact.
2. [`.github/workflows/playground-publish.yml`](.github/workflows/playground-publish.yml) runs when the first workflow finishes, with **write** permissions. It publishes the ZIP to the public `ci-artifacts` prerelease and comments the preview link.

### Why two workflow files

Because the two jobs need different permissions, and GitHub grants permissions per workflow run:

- The job that **runs pull request code** must be read-only. Pull requests from forks get a read-only `GITHUB_TOKEN`, and no repository secrets, by design.
- The job that **publishes the ZIP and comments on the pull request** needs `contents: write` and `pull-requests: write`.

A single workflow cannot do both: it would need a write-capable token in the same run that executes someone else's code, which is the well-known `pull_request_target` vulnerability. Splitting them lets a fork contributor's pull request get a working preview while keeping the privileged job away from untrusted code. `workflow_run` runs are also read from the default branch, so a pull request cannot change the logic that publishes its own preview.

The cost of the split is that the ZIP is passed between the two jobs as a workflow artifact, and the publish job verifies the artifact's pull request number and commit SHA before using its contents.

A single workflow would be simpler, and would work for pull requests from branches in this repository, but fork pull requests would get no preview at all. We accept the extra file to keep previews working for outside contributors.

### Things to know

- The Blueprint in [`assets/blueprints/blueprint.json`](assets/blueprints/blueprint.json) is the single source of truth: it sets the landing page, PHP and WP versions, login, and networking. It is also the file that WordPress.org uses for plugin directory previews.
- `bin/build-blueprint.js` adds the step that installs the plugin from the ZIP, so you never need to edit the JSON by hand.
- The ZIP must contain a single top-level directory, which WordPress and Playground both require. `bin/release.js` takes care of that, and fails the build if the entry file (`plugin.php`) or the Composer autoloader (`vendor/autoload.php`) is missing, so a broken preview is never published.
- The ZIP of each pull request is published to a public release, including for forks, since the browser has to download it from somewhere. Only the two most recent builds per pull request are kept.
- You can reproduce a preview locally: `npm run release` builds the ZIP, and `npm run blueprint -- --plugin-zip <url-or-path>` produces a Blueprint you can load in the Playground builder.

### Playground links on releases

Each release also carries a Playground link at the top of its release notes, built by the same tool: `npm run blueprint -- --plugin-zip <url> --type url` prints a ready to open Playground URL, and `--type markdown` prints that URL as a Markdown link, with `--link-text` to set its wording.

Release links point at the ZIP attached to the GitHub release, the same host the pull request previews use. The `download.fair.pm` copy of the ZIP is for people downloading the plugin, not for Playground. Playground proxies blueprint resource downloads, so the ZIP does not need to send CORS headers itself on playground.wordpress.net; the same link may not work in a self-hosted Playground that has no proxy.

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

Development uses Node 24, pinned in [`.nvmrc`](.nvmrc): `nvm use` before running `npm install`. Node 22 satisfies most of our dependencies, but some WordPress packages (`@php-wasm/*`, `@wp-playground/*`) now require Node 24.18 or newer, so CI and local installs should use 24.

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
