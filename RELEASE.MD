# Releasing a new version of FAIR Connect

## 0. Ensure adequate permissions
To perform a release of FAIR Connect, the Release Manager needs the following:
- `write` or higher permissions on the [FAIR Connect repository](https://github.com/fairpm/fair-plugin)
- `maintain` or higher permissions on the [TSC repository](https://github.com/fairpm/tsc) (this is required to post a Discussion in the Announcements category per step 9.3.)

## 1. Branching strategy overview

FAIR Connect follows a gated release branching strategy to ensure quality and control. For the complete strategy and automation details, see [Release Branching Strategy](https://github.com/fairpm/tsc/blob/main/process/release-branching.md).

### Release branch lifecycle:
1. A `release/X.Y.Z` branch is created from `main` before the release process begins
2. All version bumps and fixes for this release happen on the `release/X.Y.Z` branch
3. The release manager merges `release/X.Y.Z` → `development` for testing and RC validation
4. Once validated, the release manager merges `release/X.Y.Z` → `main` for the production release

This document assumes the `release/X.Y.Z` branch already exists.

## 2. Release types and versioning

FAIR Connect supports two types of releases, each with distinct workflows and version naming conventions:

### Production Release
A production release represents a stable, tested version ready for general use.
- **Version format:** `MAJOR.MINOR.PATCH` (e.g., `1.2.0`)
- **Merge target:** `main` branch
- **GitHub release:** Marked as the latest release
- **Announcement:** Posted to Announcements discussion

### Release Candidate (RC)
A release candidate is a pre-release version intended for testing and validation before the final production release. Multiple RCs may be created and tested before the final release.
- **Version format:** `MAJOR.MINOR.PATCH-rcN` (e.g., `1.2.0-rc1`, `1.2.0-rc2`)
- **Merge target:** `development` branch
- **GitHub release:** Marked as a pre-release (not the latest release)
- **Announcement:** Typically not announced to production users; may be shared in Discussions for testing feedback

Use the appropriate workflow below based on which release type you are performing.

## 3. Verify milestone readiness

Before starting the release process, ensure that the milestone for the upcoming release is finalized and clear.

1. Go to the FAIR Connect [**Milestones**](https://github.com/fairpm/fair-plugin/milestones) page.

   You can also access this from the repository's main page by clicking **Issues** or **Pull requests**, then **Milestones**.

2. Open the milestone corresponding to the version being released.
3. Review all issues and pull requests assigned to the milestone.
4. Confirm that:
   - All intended changes for the release have been merged, and
   - No open issues or pull requests remain in the milestone.
5. For any open or deferred issues or pull requests:
   - Move them to the next milestone, or
   - Remove the milestone if they are no longer planned for release.

Once the milestone contains no open issues or pull requests, the release is ready to proceed.

---

# Releasing a Release Candidate (RC)

Follow these steps to release an RC from the `release/X.Y.Z` branch. Multiple RCs may be created and tested before moving to the production release.

## 1. Start the release workflow

1. Go to the FAIR Connect [repository](https://github.com/fairpm/fair-plugin/actions).

2. Click the **Actions** tab.

3. In the *Actions* workflow list, select **Bump version for release**.

## 2. Configure and run the workflow

1. Click the **Run workflow** button. A workflow input panel opens.

2. Complete the following fields:

    - **Use workflow from:** Select **Branch: release/X.Y.Z**
    - **New version being released:** Enter the RC version using the format `X.Y.Z-rcN` (e.g., `1.2.0-rc1`, `1.2.0-rc2`)

3. Click the **Run workflow** button to start the release process.

4. Refresh the page to view workflow progress.

5. Click the running workflow to open the logs.

6. When the workflow finishes, it creates a pull request containing the version-bump changes.

## 3. Review and merge the version bump PR

1. Go to the **Pull requests** tab.

2. Open the version-bump pull request created by the release workflow.

3. Review the changes:

    - Update of the version number in `plugin.php`
    - Update the `VERSION` constant in `plugin.php`

4. Confirm that the changes are correct.

5. After review and approval, merge the PR to the `release/X.Y.Z` branch.

6. Go to the **Actions** tab to verify that workflow processing is complete.

7. Continue to the next step once all workflows finish.

## 4. Merge release branch to development

The `release/X.Y.Z` branch must be merged to `development` for testing and validation.

1. In the repository, create a pull request to merge `release/X.Y.Z` → `development`
2. For the PR title, use: `release: merge release/X.Y.Z into development for RC testing`
3. Ensure all checks pass and obtain required reviews per branch protection rules
4. Merge the PR to `development`
5. Continue to the next step once the merge is complete

## 5. Create a new release on GitHub

1. From the repository's main page, click the **Releases** link — or go directly to the [Releases page](https://github.com/fairpm/fair-plugin/releases).

2. Click the **Draft a new release** button.

3. In the **Select tag** field:
    - Select the tag that matches the version you just bumped to (e.g., `1.2.0-rc1`).
    - Create a new tag if it does not appear in the dropdown.
    - **Target branch:** Select `development`

4. In the **Release title** field, enter a title for the release (e.g., `1.2.0-rc1`).

5. Under **Release notes**:
   - Leave the **Describe this release** field empty or add minimal testing instructions (release notes will be generated when the final production release is created).
   - Check **Set as a pre-release** to mark this as a release candidate.

6. Click the **Save draft** button.

## 6. Finalize and publish the release

1. Return to the **Draft Release** page, if not already there.

2. Release-specific settings:
   - Do NOT check **Set as the latest release** (already marked as pre-release from step 5).
   - Optionally check **Create a discussion for this release** if you want to notify testers; if checked, you may choose a different category (e.g., General) instead of Announcements.

3. In a new browser tab, go to the repository's **Actions** tab and confirm all workflows have completed.

4. Return to the **Draft Release** page and click **Publish release**. This initiates the remaining release workflows.

5. Verify the release:
   - Visit the [Releases page](https://github.com/fairpm/fair-plugin/releases) to confirm RC is tagged as pre-release.
   - Notify relevant testers to validate the RC version.

## 7. Next steps

### If additional testing iterations are needed:
1. Continue making fixes on the `release/X.Y.Z` branch
2. Merge fixes back to `development`
3. Repeat steps 2-6 for the next RC (e.g., `1.2.0-rc2`)

### If the RC is validated and ready for production:
Proceed to the **Production Release** workflow below to merge `development` → `main` for the final release.

---

# Releasing a Production Release

Follow these steps to release the production version from `main` after the RC(s) have been validated.

## 1. Start the release workflow

1. Go to the FAIR Connect [repository](https://github.com/fairpm/fair-plugin/actions).

2. Click the **Actions** tab.

3. In the *Actions* workflow list, select **Bump version for release**.

## 2. Configure and run the workflow

1. Click the **Run workflow** button. A workflow input panel opens.

2. Complete the following fields:

    - **Use workflow from:** Select **Branch: main**
    - **New version being released:** Enter the production version using semantic versioning format (e.g., `1.2.0`)

3. Click the **Run workflow** button to start the release process.

4. Refresh the page to view workflow progress.

5. Click the running workflow to open the logs.

6. When the workflow finishes, it creates a pull request containing the version-bump changes.

## 3. Review and merge the version bump PR

1. Go to the **Pull requests** tab.

2. Open the version-bump pull request created by the release workflow.

3. Review the changes:

    - Update of the version number in `plugin.php`
    - Update the `VERSION` constant in `plugin.php`

4. Confirm that the changes are correct.

5. After review and approval, merge the PR to the `main` branch.

6. Go to the **Actions** tab to verify that workflow processing is complete.

7. Continue to the next step once all workflows finish.

## 4. Merge development into main

The `development` branch contains the tested and validated RC(s) and must be merged to `main` for the production release.

1. In the repository, create a pull request to merge `development` → `main`
2. For the PR title, use: `release: merge development into main for production release`
3. Ensure all checks pass and obtain required reviews per branch protection rules
4. Merge the PR to `main`
5. Continue to the next step once the merge is complete

## 5. Create a new release on GitHub

1. From the repository's main page, click the **Releases** link — or go directly to the [Releases page](https://github.com/fairpm/fair-plugin/releases).

2. Click the **Draft a new release** button.

3. In the **Select tag** field:
    - Select the tag that matches the version you just bumped to (e.g., `1.2.0`).
    - Create a new tag if it does not appear in the dropdown.
    - **Target branch:** Select `main`

4. In the **Release title** field, enter a title for the release (e.g., `1.2.0`).

5. Under **Release notes**:
   - Leave **Previous tag** set to `Auto`.
   - Click **Generate release notes**.
   - Review and edit the generated notes as needed.
   - You can add additional information directly in the **Describe this release** field if needed. If a teammate is preparing a release post for FAIR.pm, coordinate with them to include any relevant details.

6. Click the **Save draft** button.

## 6. Update the changelog

1. In a new browser tab, open [`CHANGELOG.md`](/CHANGELOG.md).

2. Click the pencil icon to edit the file directly in the browser.

3. Copy the release notes into `CHANGELOG.md` under the new version heading and date (e.g., `1.2.0 / 2025-12-11`).

4. Click the **Commit changes** button. In the panel that opens:
   - Select **Create a new branch for this commit and start a pull request**.
   - Enter a branch name or use the default (e.g., `update-changelog-1.2.0`).
   - Click the **Sign off and propose changes** button.

5. Create a pull request for the updated `CHANGELOG.md` file.

6. Review, approve, and merge the pull request.

## 7. Finalize and publish the release

1. Return to the **Draft Release** page.

2. Release-specific settings:
   - Check **Set as the latest release**.
   - Check **Create a discussion for this release** and choose the **Announcements** category.

3. In a new browser tab, go to the repository's **Actions** tab and confirm all workflows have completed.

4. Return to the **Draft Release** page and click **Publish release**. This initiates the remaining release workflows.

5. Verify the release:
   - Visit the [Releases page](https://github.com/fairpm/fair-plugin/releases) to confirm latest release.
   - Check any site using FAIR Connect to ensure the new version is available.
   - Verify the updated version number in the API response. (Example URL to check: https://api.fair.pm/git-updater/v1/update-api/?slug=fair-plugin)

## 8. Cleanup

1. In the repository, create a pull request to merge `release/X.Y.Z` → `main` (if not already merged as part of the development merge)
2. For the PR title, use: `release: merge release/X.Y.Z into main`
3. Ensure all checks pass and obtain required reviews per branch protection rules
4. Merge the PR to `main`
5. Delete the `release/X.Y.Z` branch to keep the repository clean
