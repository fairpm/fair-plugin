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

By default wp-env is configured with PHP 7.4 (our minimum supported version), as well as Airplane Mode to avoid inadvertent requests.

For linting and static analysis:

- `npm run lint:php:phpcs` to run PHPCS (configured in [`phpcs.xml.dist`](phpcs.xml.dist)).
- `npm run lint:php:phpstan` to run PHPStan (configured in [`phpstan.dist.neon`](phpstan.dist.neon)).
- `npm run format:php:phpcs` to automatically fix PHPCS issues.
- `npm run format:php:phpstan` to automatically fix PHPStan issues.
- `npm run cli -- composer phpstan-baseline` to update the PHPStan baseline [`tests/phpstan-baseline.neon`](tests/phpstan-baseline.neon) as you fix the reported issues.

### Configuring PHP and WP Versions

To run a specific version of PHP or WP with your local development environment, create a `.wp-env.override.json` file in the root of the repository with the following contents:

```json
{
	"phpVersion": "8.5",
	"core": "https://wordpress.org/wordpress-6.9.zip"
}
```
