# FAIR Connect

Contributors: FAIR Contributors
License: GPLv2 or later
Tags: packages, updater, installer, technical independence
Requires at least: 5.4
Requires PHP: 7.4
Tested up to: 6.9
Stable tag: 1.1.0

FAIR is a system for using **F**ederated **a**nd **I**ndependent **R**epositories in WordPress.

## Description

Many features in WordPress rely on requests to WordPress.org services, including update checks, translations, emojis, and more. Services on WordPress.org are expensive to maintain and centralized. In order to help strengthen the future of the whole WordPress ecosystem, FAIR was built to reduce reliance and burden on the central WordPress.org services.

This plugin configures your site to use FAIR implementations of the key services that are currently centralized on WordPress.org.

### Features

The FAIR Connect plugin implements federated or local versions of the following features in WordPress:

* Version checks and updates to WordPress, plugins, and themes
* Language packs and translations
* Events and News feeds in the dashboard
* Images used on the About screen, Credits screen, and elsewhere
* Browser and server health checks
* Other APIs such as the Credits API, Secret Keys API, and Importers API
* Twemoji images for emojis
* Installation and updating of packages direct from their source repository, without talking to any centralized server

The default FAIR provider in this plugin is [AspireCloud from AspirePress](https://aspirepress.org/). The AspirePress team were key in helping the FAIR project get off the ground. As the FAIR project grows and other providers come online you will be able to configure your chosen FAIR provider within the plugin.

In addition to the key FAIR implementations, a few other features in WordPress are configured by this plugin to reduce reliance and burden on other centralized external services:

* User avatars can optionally be uploaded locally as an alternative to the Gravatar service
* Media features provided by OpenVerse are disabled, pending discussion and work by the FAIR working group
* Ping services are configured to use IndexNow in place of Pingomatic

### Data Privacy

* See Also: [Linux Foundation Projects Privacy Policy](https://lfprojects.org/policies/privacy-policy/)

FAIR is built to reduce external dependencies and keep your site as self-contained as possible. However, some essential features require connecting to remote services in order to function correctly. This section details which features involve remote requests, what data may be transmitted, and the specific third-party providers involved. Review the list below to understand exactly where and why your site may communicate with external endpoints.

* Search engine pings when a post is published are handled by [IndexNow](https://www.indexnow.org).
* Installation and updates of all WordPress Packages (core, plugins, themes) are via [AspireCloud from AspirePress](https://aspirepress.org/) (or other mirror as configured).
* PHP versions are provided by [php.net](https://php.net)
* Twemoji (emoji assets) are retrieved by [jsDeliver](https://cdn.jsdelivr.net/gh/jdecked/twemoji@15.1.0/assets/)

In addition we self-host certain features that could not be properly protected on our API servers as an intermediary. All data collected from FAIR servers fall under the [Linux Foundation Policies and Terms of Use for Hosted Projects](https://lfprojects.org/policies/hosted-project-tools-terms-of-use/); additionally, [the server itself is open source](https://github.com/fairpm/server). These services include:

* WordPress Events (`https://api.fair.pm/fair/v1/events`) - Retrieved from [The WP World](https://thewp.world) hourly and then cached on our servers. No user data is sent to The WP World.
* WordPress Planet/News (`https://planet.fair.pm/atom.xml`)


## Installation

FAIR can be installed manually by downloading the latest zip from releases and install it via your WordPress admin dashboard.

* [FAIR Releases](https://github.com/fairpm/fair-plugin/releases)
* [How To Manually Install a Plugin](https://wordpress.org/documentation/article/manage-plugins/#manual-plugin-installation-1)

### Uninstallation

To remove the FAIR Connect plugin and its features, you can deactivate and delete the plugin. There are no changes made to your database outside of the plugin settings, and no external files are edited. FAIR Connect is a self-contained plugin, using the accepted WordPress standards and practices.


## Screenshots

1. Add Plugins page with FAIR Package Manager installed
2. Plugin details modal for a FAIR-managed plugin
3. Direct Install page to install a new pluging by DID
4. Avatar Source setting to manage FAIR Avatars or Gravatar
5. Updated WordPress News and Events dashboard widget
6. WordPress update page showing AspireCloud as the update source for core themes
