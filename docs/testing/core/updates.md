# Testing FAIR Connect: Core - Updates

This document details how to test the various ways to update WordPress core.

> [!NOTE]
> **Expected behavior**
> 
> This document does not include expected behavior as this depends on the changes you're testing. If you're unsure, ask the author of the pull request to outline what the expected behavior is. 
> 
> Tell the author of the pull request about _any_ behavior which they did not mention. Even if it makes sense to you, it may not be the desired behavior.

## Table of Contents

- [WP Admin](#wp-admin)
  - [Dashboard > Updates > Update to a newer version](#dashboard--updates--update-to-a-newer-version)
  - [Dashboard > Updates > Reinstall the current version](#dashboard--updates--reinstall-the-current-version)
- [WP-CLI](#wp-cli)
  - [CoreCommand: Update to a newer version](#corecommand-update-to-a-newer-version)
  - [CoreCommand: Reinstall the current version](#corecommand-reinstall-the-current-version)
  - [CoreCommand: Downgrade to an older version](#corecommand-downgrade-to-an-older-version)
- [Automatic update](#automatic-update)
  - [Automatically update a WordPress core major version](#automatically-update-a-wordpress-core-major-version)
  - [Automatically update a WordPress core minor version](#automatically-update-a-wordpress-core-minor-version)

## WP Admin

- ### Dashboard > Updates > Update to a newer version

    | Step | Action                                            |
    |------|---------------------------------------------------|
    | 1    | Ensure you are running an older version of Core   |
    | 2    | Navigate to `Dashboard > Updates`                 |
    | 3    | Click the `Update Now` button                     |

- ### Dashboard > Updates > Reinstall the current version

    | Step | Action                                            |
    |------|---------------------------------------------------|
    | 1    | Navigate to `Dashboard > Updates`                 |
    | 2    | Click the `Re-install version <version>` button   |

## WP-CLI

- ### CoreCommand: Update to a newer version

    | Step | Action                                            |
    |------|---------------------------------------------------|
    | 1    | Run `wp core update`                              |

- ### CoreCommand: Reinstall the current version

    | Step | Action                                                     |
    |------|------------------------------------------------------------|
    | 1    | Run `wp core update --version="<current version>" --force` |

- ### CoreCommand: Downgrade to an older version

    | Step | Action                                                   |
    |------|----------------------------------------------------------|
    | 1    | Run `wp core update --version="<older version>" --force` |

## Automatic update

- ### Automatically update a WordPress core major version

    | Step | Action                                                               |
    |------|----------------------------------------------------------------------|
    | 1    | Ensure you are running an older version of Core                      |
    | 2    | Wait for the scheduled automatic update to run                       |
    | 3    | Check `Dashboard > Updates` after the automatic update has completed |

- ### Automatically update a WordPress core minor version

    | Step | Action                                                                                  |
    |------|-----------------------------------------------------------------------------------------|
    | 1    | Ensure you are running an older version of Core                                         |
    | 2    | Navigate to `Dashboard > Updates`                                                       |
    | 3    | Click the `Switch to automatic updates for maintenance and security releases only` link |
    | 4    | Wait for the scheduled automatic update to run                                          |
    | 5    | Check `Dashboard > Updates` after the automatic update has completed                    |

> [!NOTE]
> There are ways to force automatic updates to trigger instantly or on a reduced interval.
> 
> If this was done during testing, document this in your test report.
