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

```sh
# Install wp-env and other dependencies.
$ npm install

# Start the development server
$ npm run env start

# Get the logs
$ npm run env logs

# Stop the development server
$ npm run env stop
```

By default, wp-env is configured with PHP 7.4 (our minimum supported version), as well as Airplane Mode to avoid inadvertent requests.
