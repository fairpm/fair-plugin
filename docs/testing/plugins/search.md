# Testing FAIR Connect: Plugins - Search

This document details how to test the various ways to search for a plugin by its DID.

Test using all of the [test plugins](./README.md#plugins-to-test-with) to ensure all search paths work as intended.

> [!NOTE]
> **Expected behavior**
> 
> This document does not include expected behavior as this depends on the changes you're testing. If you're unsure, ask the author of the pull request to outline what the expected behavior is. 
> 
> Tell the author of the pull request about _any_ behavior which they did not mention. Even if it makes sense to you, it may not be the desired behavior.

## Table of Contents

- [WP Admin](#wp-admin)
  - [Plugins > Add New](#plugins--add-new)
  - [Plugins > Add New > Direct Install tab](#plugins--add-new--direct-install-tab)
- [WP-CLI](#wp-cli)
  - [PluginCommand: Search](#plugincommand-search)

## WP Admin

- ### Plugins > Add New

    | Step | Action                          |
    |------|---------------------------------|
    | 1    | Navigate to `Plugins > Add New` |
    | 2    | Search for the plugin's DID     |

- ### Plugins > Add New > Direct Install tab

    | Step | Action                                          |
    |------|-------------------------------------------------|
    | 1    | Navigate to `Plugins > Add New`                 |
    | 2    | Click the `Direct Install` tab                  |
    | 3    | Enter the plugin's DID                          |
	| 4    | Click the `View Details` button                 |


## WP-CLI

- ### PluginCommand: Search
    
    | Step | Action                       |
    |------|------------------------------|
    | 1    | Run `wp plugin search <DID>` |
