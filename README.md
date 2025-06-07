# FAIR

FAIR is a system for using **F**ederated **a**nd **I**ndependent **R**epositories in WordPress.

This repository contains the plugin for installation into WordPress.

## Description

This plugin prevents all calls to WordPress.org, WordPress.com, Gravatar.com, and any other connection to Automattic servers from the core WordPress code.

### Features

* Credits - Replace calls to the update API for version information. Prevent loading Gravatars for contributors.
* Default Repository - Changes the default repository for extensions to `api.aspirecloud.net`
* Importers - Replace the retrieval of popular import plugins.
* Openverse - Disable the media category in the block editor.
* Salts - Prevents calls to the WordPress.org API for salt generation.
* Version Check - Prevents calls to the WordPress.org API for version checks.

### Assets

Assets normally hosted on WordPress.org or the WordPress.com CDN will be hosted on `{{ LOCATION_TBD }}`.

* Language Packs
* Credit Page images
* Twemoji

## Contributing

See [CONTRIBUTING.md](./CONTRIBUTING.md) for information on contributing.

The FAIR plugin is currently maintained by the Technical Independence Working Group, in conjunction with the FAIR Working Group.

FAIR is licensed under the GNU General Public License, v2 or later. Copyright 2025 the contributors.

WordPress is a registered trademark of the WordPress Foundation. FAIR is not endorsed by, or affiliated with, the WordPress Foundation.
