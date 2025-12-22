# Releasing a new version of FAIR Connect

## 1. Verify milestone readiness

Before starting the release process, ensure that the milestone for the upcoming release is finalized and clear.

1. Go to the FAIR Connect [**Milestones**](https://github.com/fairpm/fair-plugin/milestones) page.

   You can also access this from the repository’s main page by clicking **Issues** or **Pull requests**, then **Milestones**.

2. Open the milestone corresponding to the version being released.
3. Review all issues and pull requests assigned to the milestone.
4. Confirm that:
   - All intended changes for the release have been merged, and
   - No open issues or pull requests remain in the milestone.
5. For any open or deferred issues or pull requests:
   - Move them to the next milestone, or
   - Remove the milestone if they are no longer planned for release.

Once the milestone contains no open issues or pull requests, the release is ready to proceed.

## 2. Start the release workflow

1. Go to the FAIR Connect [repository](https://github.com/fairpm/fair-plugin/actions).

2. Click the **Actions** tab.

3. In the *Actions* workflow list, select **Bump version for release**.

## 3. Configure and run the workflow

1. Click the **Run workflow** button. A workflow input panel opens.

2. Complete the following fields:

    - **Use workflow from:** Select **Branch: main** (default).
    - **New version being released:** Enter the release version number (e.g., `1.0.0`). FAIR Connect uses semantic versioning (`MAJOR.MINOR.PATCH`).

3. Click the **Run workflow** button to start the release process.

4. Refresh the page to view workflow progress.

5. Click the running workflow to open the logs.

6. When the workflow finishes, it creates a pull request containing the version-bump changes.

## 4. Review and merge the version bump PR

1. Go to the **Pull requests** tab.

2. Open the version-bump pull request created by the release workflow.

3. Review the changes:

    - Update of the version number in `plugin.php`
    - Update the `VERSION` constant in `plugin.php`

4. Confirm that the changes are correct.

5. After review and approval, merge the PR to the `main` branch.

6. Go to the **Actions** tab to verify that workflow processing is complete.

7. Continue to the next step once all workflows finish.

## 5. Create a new release on GitHub and update CHANGELOG.md

1. From the repository’s main page, click the **Releases** link — or go directly to the [Releases page](https://github.com/fairpm/fair-plugin/releases).

2. Click the **Draft a new release** button.

3. In the **Select tag** field:
    - Select the tag that matches the version you just bumped to (e.g., `1.2.0`).
    - Create a new tag if it does not appear in the dropdown.

4. In the **Release title** field, enter a title for the release (e.g., `1.2.0`).

5. Under **Release notes**:
   - Leave **Previous tag** set to `Auto`.
   - Click **Generate release notes**.
   - Review and edit the generated notes as needed.
   - Copy/paste the release notes into the **CHANGELOG.md** file under the new version heading and date.

> [!TIP]
> You can add additional information directly in the **Describe this release** field.
> If a teammate is preparing a release post for FAIR.pm, coordinate with them to include any relevant details.

6. Check **Set as the latest release**.

7. Check **Create a discussion for this release** and choose the **Announcements** category.

8. In a new browser tab, go to the repository’s **Actions** tab and confirm all workflows have completed.

9. Return to the **Draft Release** page and click **Publish release**.

10. To verify release processing:
    - Go to the **Actions** tab.
    - Once workflows complete, the release is live.

11. Verify successful release
    - Visit the [Releases page](https://github.com/fairpm/fair-plugin/releases) to confirm latest release.
    - Check any site using FAIR Connect to ensure the new version is available.
    - Check the API response for the updated version number.
