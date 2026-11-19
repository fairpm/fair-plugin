# Testing FAIR Connect: Plugins - Updates

This document details how to test the various ways to update a plugin.

Test using all of the [test plugins](./README.md#plugins-to-test-with) to ensure all update paths work as intended.

> [!NOTE]
> **Expected behavior**
> 
> This document does not include expected behavior as this depends on the changes you're testing. If you're unsure, ask the author of the pull request to outline what the expected behavior is. 
> 
> Tell the author of the pull request about _any_ behavior which they did not mention. Even if it makes sense to you, it may not be the desired behavior.

## Table of Contents

- [WP Admin](#wp-admin)
  - [Dashboard > Updates](#dashboard--updates)
  - [Plugins > Installed plugins > Update link](#plugins--installed-plugins--update-link)
  - [Plugins > Installed plugins > Bulk Actions](#plugins--installed-plugins--bulk-actions)
  - [Plugins > Add New > Search by name and update from card](#plugins--add-new--search-by-name-and-update-from-card)
  - [Search by name and update from modal](#search-by-name-and-update-from-modal)
  - [Search by DID and update from modal](#search-by-did-and-update-from-modal)
  - [Direct Install tab: Enter DID and update from modal](#direct-install-tab-enter-did-and-update-from-modal)
  - [Upload ZIP](#upload-zip)
- [WP-CLI](#wp-cli)
  - [Update by slug](#update-by-slug)
  - [Update by DID](#update-by-did)
  - [Bulk update](#bulk-update)
  - [Forced downgrade](#forced-downgrade)
- [Filesystem](#filesystem)
  - [Local Filesystem: Extract an update ZIP file's contents](#local-filesystem-extract-an-update-zip-files-contents)
  - [FTP: Extract and upload an update ZIP file's contents](#ftp-extract-and-upload-an-update-zip-files-contents)
- [Automatic update](#automatic-update)
  - [Automatically update a plugin](#automatically-update-a-plugin)


## WP Admin

- ### Dashboard > Updates

    | Step | Action                            |
    |------|-----------------------------------|
    | 1    | Navigate to `Dashboard > Updates` |
    | 2    | Find the plugin in the list       |
    | 3    | Check its checkbox                |
    | 4    | Click the `Update plugins` button |

- ### Plugins > Installed plugins > Update link

    | Step | Action                                           |
    |------|--------------------------------------------------|
    | 1    | Navigate to `Plugins > Installed plugins`        |
    | 2    | Find the plugin in the list                      |
    | 3    | In the plugin's row, click the `update now` link |

- ### Plugins > Installed plugins > Bulk Actions

    | Step | Action                                                                              |
    |------|-------------------------------------------------------------------------------------|
    | 1    | Navigate to `Plugins > Installed plugins`                                           |
    | 2    | Find the plugin in the list                                                         |
    | 3    | Check its checkbox                                                                  |
    | 4    | At the top of the page, in the `Bulk Actions` dropdown, select the `Update` option  |
    | 5    | Click the `Apply` button                                                            |

- ### Plugins > Add New > Search by name and update from card

    | Step | Action                                          |
    |------|-------------------------------------------------|
    | 1    | Navigate to `Plugins > Add New`                 |
    | 2    | Search for the plugin by its name               |
    | 3    | In the plugin's card, click the `Update` button | 

- ### Plugins > Add New > Search by DID and update from card

    | Step | Action                                          |
    |------|-------------------------------------------------|
    | 1    | Navigate to `Plugins > Add New`                 |
    | 2    | Search for the plugin by its DID                |
    | 3    | In the plugin's card, click the `Update` button |

- ### Search by name and update from modal

    | Step | Action                                               |
    |------|------------------------------------------------------|
    | 1    | Navigate to `Plugins > Add New`                      |
    | 2    | Search for the plugin by its name                    |
    | 3    | In the plugin's card, click the `More details` link  |
    | 4    | In the modal that appears, click the `Update` button |

- ### Search by DID and update from modal

    | Step | Action                                               |
    |------|------------------------------------------------------|
    | 1    | Navigate to `Plugins > Add New`                      |
    | 2    | Search for the plugin by its DID                     |
    | 3    | In the plugin's card, click the `More details` link  |
    | 4    | In the modal that appears, click the `Update` button |

- ### Direct Install tab: Enter DID and update from modal

    | Step | Action                                               |
    |------|------------------------------------------------------|
    | 1    | Navigate to `Plugins > Add New`                      |
    | 2    | Click the `Direct Install` tab                       |
    | 3    | Enter the plugin's DID                               |
    | 4    | Click the `View Details` button                      |
    | 5    | Click the `Update` button                            |

- ### Upload ZIP

    | Step | Action                                              |
    |------|-----------------------------------------------------|
    | 1    | Navigate to `Plugins > Add New`                     |
    | 2    | At the top of the screen, click the `Upload` button |
    | 3    | Select the plugin's ZIP file from your local device |
    | 4    | Click the `Install Now` button                      |
    | 5    | Click the `Replace installed with uploaded` button  |

## WP-CLI

- ### Update by slug

    | Step | Action                        |
    |------|-------------------------------|
    | 1    | Run `wp plugin update <slug>` |


- ### Update by DID

    | Step | Action                       |
    |------|------------------------------|
    | 1    | Run `wp plugin update <DID>` |

- ### Bulk update

    | Step | Action                       |
    |------|------------------------------|
    | 1    | Run `wp plugin update --all` |

- ### Forced downgrade

    | Step | Action                                                     |
    |------|------------------------------------------------------------|
    | 1    | Run `wp plugin update --version="<older version>" --force` |

## Filesystem

- ### Local Filesystem: Extract an update ZIP file's contents

    | Step | Action                                                                |
    |------|-----------------------------------------------------------------------|
    | 1    | Find the plugin's update ZIP file on your local device                |
    | 2    | Extract its contents to your website's `wp-content/plugins` directory |

- ### FTP: Extract and upload an update ZIP file's contents

    | Step | Action                                                    |
    |------|-----------------------------------------------------------|
    | 1    | Find the plugin's update ZIP file on your local device    |
    | 2    | Extract its contents to your local device                 |
    | 3    | Connect to your website's filesystem using FTP            |
    | 4    | Navigate to your website's `wp-content/plugins` directory |
    | 5    | Upload the extracted contents                             |

## Automatic update

- ### Automatically update a plugin

    | Step | Action                                                    |
    |------|-----------------------------------------------------------|
    | 1    | Navigate to `Plugins > Installed plugins`                 |
    | 2    | In each plugin's row, click `Enable automatic updates`    |
    | 3    | Check the plugin after the automatic update has completed |

> [!NOTE]
> There are ways to force automatic updates to trigger instantly or on a reduced interval.
> 
> If this was done during testing, document this in your test report.
