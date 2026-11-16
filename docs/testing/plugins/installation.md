# Testing FAIR Connect: Plugins - Installation

This document details how to test the various ways to install a plugin.

Test using all of the [test plugins](./README.md#plugins-to-test-with) to ensure all installation paths work as intended.

> [!NOTE]
> **Expected behavior**
> 
> This document does not include expected behavior as this depends on the changes you're testing. If you're unsure, ask the author of the pull request to outline what the expected behavior is. 
> 
> Tell the author of the pull request about _any_ behavior which they did not mention. Even if it makes sense to you, it may not be the desired behavior.

## Table of Contents

- [WP Admin](#wp-admin)
  - [Search by name and install from card](#search-by-name-and-install-from-card)
  - [Search by DID and install from card](#search-by-did-and-install-from-card)
  - [Search by name and install from modal](#search-by-name-and-install-from-modal)
  - [Search by DID and install from modal](#search-by-did-and-install-from-modal)
  - [Upload ZIP](#upload-zip)
- [WP-CLI](#wp-cli)
  - [Install by slug](#install-by-slug)
  - [Install by slug with immediate activation](#install-by-slug-with-immediate-activation)
  - [Install by DID](#install-by-did)
  - [Install by DID with immediate activation](#install-by-did-with-immediate-activation)
- [Filesystem](#filesystem)
  - [Local Filesystem: Extract a ZIP file's contents](#local-filesystem-extract-a-zip-files-contents)
  - [FTP: Extract and upload a ZIP file's contents](#ftp-extract-and-upload-a-zip-files-contents)

## WP Admin

- ### Search by name and install from card

    | Step | Action                                            |
    |------|---------------------------------------------------|
    | 1    | Navigate to `Plugins > Add New`                   |
    | 2    | Search for the plugin by its name                 |
    | 3    | In the plugin's card, click the `Install` button  |
    | 4    | In the plugin's card, click the `Activate` button |


- ### Search by DID and install from card

    | Step | Action                                            |
    |------|---------------------------------------------------|
    | 1    | Navigate to `Plugins > Add New`                   |
    | 2    | Search for the plugin by its DID                  |
    | 3    | In the plugin's card, click the `Install` button  |
    | 4    | In the plugin's card, click the `Activate` button |

- ### Search by name and install from modal

    | Step | Action                                                |
    |------|-------------------------------------------------------|
    | 1    | Navigate to `Plugins > Add New`                       |
    | 2    | Search for the plugin by its name                     |
    | 3    | In the plugin's card, click the `More details` link   |
    | 4    | In the modal that appears, click the `Install` button |
    | 5    | In the modal, click the `Activate` button             |

- ### Search by DID and install from modal

    | Step | Action                                                |
    |------|-------------------------------------------------------|
    | 1    | Navigate to `Plugins > Add New`                       |
    | 2    | Search for the plugin by its DID                      |
    | 3    | In the plugin's card, click the `More details` link   |
    | 4    | In the modal that appears, click the `Install` button |
    | 5    | In the modal, click the `Activate` button             |

- ### Upload ZIP

    | Step | Action                                              |
    |------|-----------------------------------------------------|
    | 1    | Navigate to `Plugins > Add New`                     |
    | 2    | At the top of the screen, click the `Upload` button |
    | 3    | Select the plugin's ZIP file from your local device |
    | 4    | Click the `Install Now` button                      |
    | 5    | Click the `Activate` button                         |

## WP-CLI

- ### Install by slug

    | Step | Action                          |
    |------|---------------------------------|
    | 1    | Run `wp plugin install <slug>`  |
    | 2    | Run `wp plugin activate <slug>` |

- ### Install by slug with immediate activation

    | Step | Action                                    |
    |------|-------------------------------------------|
    | 1    | Run `wp plugin install --activate <slug>` |

- ### Install by DID

    | Step | Action                          |
    |------|---------------------------------|
    | 1    | Run `wp plugin install <DID>`   |
    | 2    | Run `wp plugin activate <slug>` |

- ### Install by DID with immediate activation

    | Step | Action                                   |
    |------|------------------------------------------|
    | 1    | Run `wp plugin install --activate <DID>` |

## Filesystem

- ### Local Filesystem: Extract a ZIP file's contents

    | Step | Action                                                                |
    |------|-----------------------------------------------------------------------|
    | 1    | Find the plugin's ZIP file on your local device                       |
    | 2    | Extract its contents to your website's `wp-content/plugins` directory |
    | 3    | Navigate to `Plugins > Installed plugins`                             |
    | 4    | Find the plugin in the list                                           |
    | 5    | Click the `Activate` link                                             |

- ### FTP: Extract and upload a ZIP file's contents

    | Step | Action                                                    |
    |------|-----------------------------------------------------------|
    | 1    | Find the plugin's ZIP file on your local device           |
    | 2    | Extract its contents to your local device                 |
    | 3    | Connect to your website's filesystem using FTP            |
    | 4    | Navigate to your website's `wp-content/plugins` directory |
    | 5    | Upload the extracted contents                             |
    | 6    | Navigate to `Plugins > Installed plugins`                 |
    | 7    | Find the plugin in the list                               |
    | 8    | Click the `Activate` link                                 |
