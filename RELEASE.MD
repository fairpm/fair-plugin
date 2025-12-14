# Releasing a new version of FAIR Connect

## 1. Start the release workflow

1. Go to the FAIR Connect [repository](https://github.com/fairpm/fair-plugin/actions).
2. Click the **Actions** tab.
3. In the *Actions* workflow list, select **Bump version for release**.

## 2. Configure and run the workflow

1. Click the **Run workflow** button. A workflow input panel opens.
2. Complete the following fields:

- **Use workflow from:** Select **Branch: main** (default).
- **New version being released:** Enter the release version number (e.g., `1.0.0`).
  FAIR Connect uses semantic versioning (`MAJOR.MINOR.PATCH`).

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

## 4. Review and merge the Generate POT PR

1. Go to the **Pull requests** tab.
2. Open the most recent **Generate POT** pull request.
3. Review the changes:

    - Update `POT-creation-date` in `languages/fair.pot`
    - Update package DID in `languages/fair.pot`
    - Update package URL in `languages/fair.pot`

4. Confirm that the changes are correct.
5. After review and approval, merge the PR to the `main` branch.
6. Go to the **Actions** tab to confirm workflow completion.

## 5. Create a new release on GitHub

1. From the repository’s main page, click the **Releases** link — or go directly to the [Releases page](https://github.com/fairpm/fair-plugin/releases).
2. Click the **Draft a new release** button.
3. In the **Select tag** field:
    - select the tag that matches the version you just released (e.g., `1.2.0`).
    - Create a new tag if it does not appear in the dropdown.

4. In the **Release title** field, enter a title for the release (e.g., `1.2.0`).

5. Under **Release notes**:
   - Leave **Previous tag** set to `Auto`.
   - Click **Generate release notes**.
   - Review and edit the generated notes as needed.

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

11. Visit the [Releases page](https://github.com/fairpm/fair-plugin/releases) to confirm.

## 6. (Optional Cleanup) Move unmerged PRs to the next milestone

Any unmerged PRs intended for the just-released version should be moved to the next milestone.

1. Go to the **Pull requests** tab.
2. For each applicable PR, do the following:
   1. Open the pull request.
   2. In the PR sidebar, locate the **Milestone** field.
   3. Select the next milestone from the **Milestone** dropdown.
   4. Repeat as needed for all unmerged PRs.
