# FAIR

FAIR is a system for using **F**ederated **a**nd **I**ndependent **R**epositories in WordPress.

This repository contains the plugin for installation into WordPress.

## Description

Many features in WordPress rely on requests to WordPress.org services, including update checks, translations, emojis, and more. Services on WordPress.org are expensive to maintain and centralized. In order to help strengthen the future of the whole WordPress ecosystem, FAIR was built to reduce reliance and burden on the central WordPress.org services.

This plugin configures your site to use FAIR implementations of the key services that are currently centralized on WordPress.org.

### Features

> [!NOTE]  
> The FAIR project is brand new. This plugin is a pre-release and some features are yet to be fully implemented.

The FAIR plugin implements federated or local versions of the following features in WordPress:

* Version checks and updates to WordPress, plugins, and themes
* Language packs and translations
* Events and News feeds in the dashboard
* Images used on the About screen, Credits screen, and elsewhere
* Browser and server health checks
* Other APIs such as the Credits API, Secret Keys API, and Importers API
* Twemoji images for emojis

The default FAIR provider in this plugin is [AspireCloud from AspirePress](https://aspirepress.org/). The AspirePress team were key in helping the FAIR project get off the ground. As the FAIR project grows and other providers come online you will be able to configure your chosen FAIR provider within the plugin.

In addition to the key FAIR implementations, a few other features in WordPress are configured by this plugin to reduce reliance and burden on other centralized external services:

* User avatars can optionally be uploaded locally as an alternative to the Gravatar service
* Media features provided by OpenVerse are disabled, pending discussion and work by the FAIR working group
* Ping services are configured to use IndexNow in place of Pingomatic

## Contributing

See [CONTRIBUTING.md](./CONTRIBUTING.md) for information on contributing.

The FAIR plugin is currently maintained by the Technical Independence Working Group, in conjunction with the FAIR Working Group.

FAIR is licensed under the GNU General Public License, v2 or later. Copyright 2025 the contributors.

WordPress is a registered trademark of the WordPress Foundation. FAIR is not endorsed by, or affiliated with, the WordPress Foundation.
