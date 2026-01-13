# FAIR Repository Publishing Workflow

This GitHub Actions workflow automatically publishes your WordPress plugin to a FAIR (Federated Asset Integrity Registry) repository, implementing the [FAIR Protocol](https://github.com/fairpm/fair-protocol) for decentralized package management.

## Quick Start: Publish Your Plugin to FAIR in 5 Steps

Follow these steps in order to publish your plugin to the FAIR repository:

### Step 1: Add Workflow Files to Your Repository

Copy all the workflow files to your repository:

```bash
# Your repository structure should look like this:
.github/
├── scripts/
│   └── fair/
│       ├── create-did.php
│       ├── generate-keys-local.php
│       ├── generate-metadata.php
│       ├── manage-keys.php
│       ├── sign-artifact.php
│       └── update-did-service.php
└── workflows/
    ├── fair-publish.yml
    └── fair-release.yml  # (you should already have this)
```

Commit and push these files:
```bash
git add .github/
git commit -m "Add FAIR publishing workflow"
git push
```

### Step 2: Generate Cryptographic Keys Locally

**Important:** For security, keys are generated on YOUR machine, not on GitHub.

```bash
# Make sure you're in your plugin directory
cd path/to/your-plugin

# Run the key generator
php .github/scripts/fair/generate-keys-local.php
```

The script will display 4 keys. **Keep this terminal window open** - you'll need to copy these keys in the next step.

### Step 3: Add Keys as GitHub Secrets

1. Go to your GitHub repository
2. Click on **Settings** (top menu)
3. In the left sidebar, click **Secrets and variables** → **Actions**
4. Click the **New repository secret** button
5. Add each of these 4 secrets (copy the values from your terminal):

   | Secret Name | Copy from terminal |
   |------------|-------------------|
   | `FAIR_ROTATION_KEY_PRIVATE` | First key displayed |
   | `FAIR_ROTATION_KEY_PUBLIC` | Second key displayed |
   | `FAIR_VERIFICATION_KEY_PRIVATE` | Third key displayed |
   | `FAIR_VERIFICATION_KEY_PUBLIC` | Fourth key displayed |

6. After adding all 4 secrets, you can close the terminal (or clear the history for security)

### Step 4: Create a Release

The FAIR publish workflow triggers automatically after the release workflow completes.

**Option A: Create a release via tag** (Recommended)
```bash
# Create and push a new version tag
git tag v1.0.0
git push origin v1.0.0
```

**Option B: Create a release manually**
1. Go to your repository on GitHub
2. Click **Releases** → **Draft a new release**
3. Click **Choose a tag** → Type your version (e.g., `v1.0.0`) → **Create new tag**
4. Fill in the release title and description
5. Click **Publish release**

### Step 5: Wait for FAIR Publishing to Complete

After the release workflow completes:

1. The **"Publish to FAIR Repository"** workflow will automatically start
2. Go to the **Actions** tab to monitor progress
3. The workflow will:
   - Create a DID for your plugin (first time only)
   - Download the release asset
   - Sign the package with your verification key
   - Generate FAIR metadata
   - Upload `fair-metadata.json` to the release
   - Register with the PLC directory

4. Once complete, check the release - you'll see `fair-metadata.json` attached

**🎉 Congratulations!** Your plugin is now published to the FAIR repository!

### Step 6: Save Your DID (First Publish Only)

**Important:** After your FIRST successful publish, you must save the generated DID as a repository **variable**.

1. Go to the **Actions** tab in your repository
2. Click on the latest **"Publish to FAIR Repository"** workflow run
3. Check the **Summary** section at the top - your DID will be displayed in a box
4. Copy the DID value (format: `did:plc:...`)
5. Add it as a repository **variable** (not a secret):
   - Go to: **Settings** → **Secrets and variables** → **Actions**
   - Click the **"Variables"** tab
   - Click **"New repository variable"**
   - Name: `FAIR_DID`
   - Value: (paste the DID, e.g., `did:plc:abc123xyz...`)
   - Click **"Add variable"**

**Why use Variables instead of Secrets?** DIDs contain special characters (like `:`) that aren't allowed in secrets. Variables are perfect for non-sensitive identifiers like DIDs.

**Why save it?** This DID identifies your plugin in the FAIR protocol. Saving it as a variable allows future releases to use the same identity instead of creating a new DID each time.

This step is only needed once. Future publishes will automatically use this stored DID.

---

## What Happens Behind the Scenes

When you create a release, here's the full flow:

1. **Release Workflow** (`fair-release.yml`) runs:
   - Builds plugin ZIP
   - Creates GitHub release
   - Uploads ZIP as release asset

2. **FAIR Publish Workflow** (`fair-publish.yml`) runs automatically:
   - Validates cryptographic keys exist
   - Creates or uses existing DID
   - Downloads the release ZIP
   - Signs the ZIP with verification key
   - Generates FAIR metadata
   - Uploads metadata to release

3. **Your plugin is now available** via FAIR protocol!

---

## Additional Information

### How It Works

### Workflow Trigger

The workflow runs in two scenarios:

1. **Automatically after release** - Triggers when the `fair-release.yml` workflow completes successfully (when a new tag is pushed)
2. **Manual dispatch** - Can be manually triggered from the Actions tab with an optional version parameter

#### Cryptographic Keys

For security, cryptographic keys are **NEVER generated in GitHub Actions**. Instead:

1. **Generate keys locally** on your machine using `generate-keys-local.php`
2. **Copy them** to GitHub Secrets manually
3. **Keys never leave your machine** during generation

This ensures maximum security as private keys are never exposed to GitHub's infrastructure.

#### DID Creation

The workflow creates a PLC DID (Decentralized Identifier) for your package:
- Uses the generated cryptographic keys
- Submits to the PLC directory at `https://plc.directory`
- Adds a FAIR service endpoint pointing to your metadata
- Stores the DID as a secret for future use

#### Package Signing and Publishing

For each release:
1. Downloads the release ZIP created by `fair-release.yml`
2. Calculates SHA-256 checksum
3. Signs the package with the verification key
4. Generates FAIR-compliant `metadata.json`
5. Uploads metadata to the GitHub release

### Prerequisites (Before Starting)

Before following the setup steps above, ensure you have:

- A WordPress plugin with a main plugin file containing standard headers
- A GitHub repository with releases enabled
- PHP installed locally (for key generation)
- The `fair-release.yml` workflow (or similar) that creates releases from tags

### Manual Publishing (Optional)

To manually publish a specific version outside of the automatic release flow:

1. Go to **Actions** tab in your repository
2. Select **"Publish to FAIR Repository"** workflow
3. Click **"Run workflow"** button (top right)
4. Optionally enter a version (e.g., `v0.0.1`) or leave empty to use the latest tag
5. Click **"Run workflow"**

---

## Repository Secrets & Variables

The workflow requires these secrets and variables:

### Secrets (Sensitive Keys)

| Secret Name | Purpose | How to Add |
|------------|---------|------------|
| `FAIR_ROTATION_KEY_PRIVATE` | DID rotation key (secp256k1) | Generated locally in Step 2, added manually in Step 3 |
| `FAIR_ROTATION_KEY_PUBLIC` | DID rotation public key | Generated locally in Step 2, added manually in Step 3 |
| `FAIR_VERIFICATION_KEY_PRIVATE` | Package signing key (Ed25519) | Generated locally in Step 2, added manually in Step 3 |
| `FAIR_VERIFICATION_KEY_PUBLIC` | Package verification public key | Generated locally in Step 2, added manually in Step 3 |

### Variables (Non-Sensitive Identifiers)

| Variable Name | Purpose | How to Add |
|--------------|---------|------------|
| `FAIR_DID` | Your package's DID identifier | Generated by first workflow run, **you must add manually** as a variable (see Step 6) |

**Important**: Keys are generated locally using `generate-keys-local.php` for maximum security. Never generate private keys in GitHub Actions.

---
### Automatic Publishing

The workflow runs automatically whenever:
- You push a new tag (e.g., `v1.0.0`)
- The `fair-release.yml` workflow completes successfully

No manual intervention required after initial setup!

### Manual Publishing

To manually publish a specific version:

1. Go to **Actions** tab in your repository
2. Select **"Publish to FAIR Repository"** workflow
3. Click **"Run workflow"**
4. Optionally enter a version (leave empty to use latest tag)
5. Click **"Run workflow"**

## Workflow Steps

### 1. Key Management
- Checks for existing cryptographic keys
- Generates new keys if none exist
- Saves keys as repository secrets
 in secrets
- Keys must be generated locally beforehand (never in Actions)
- Fails if keys are missing with instructionsting one
- Submits to PLC directory
- Updates with FAIR service endpoint

### 3. Package Building
- Creates clean plugin ZIP archive
- Excludes development files (`.git`, `node_modules`, etc.)
- Calculates SHA-256 checksum

### 4. Artifact Signing
- Signs the package ZIP with verification key
- Generates cryptographic signature
- Ensures package integrity

### 5. Metadata Generation
- Parses plugin headers and `readme.txt`
- Generates FAIR-compliant `metadata.json`
- Includes release information, dependencies, and artifact details

### 6. Publishing
- Uploads `fair-metadata.json` to GitHub release
- Makes metadata available at predictable URL

## FAIR Metadata

The generated metadata includes:

- **Package information**: name, description, slug, license
- **Authors and security contacts**
- **Release details**: version, dependencies, requirements
- **Artifacts**: package ZIP with signature and checksum
- **Documentation sections**: changelog, description, installation, etc.

Example metadata structure:
```json
{
  "@context": "https://fair.pm/ns/metadata/v1",
  "id": "did:plc:abc123...",
  "type": "wp-plugin",
  "name": "Minimal Admin",
  "license": "GPL-2.0+",
  "releases": [
    {
      "version": "1.0.0",
      "artifacts": {
        "package": {
          "url": "https://github.com/user/repo/releases/download/v1.0.0/plugin.zip",
          "signature": "zQ3sh...",
          "checksum": "sha256:abc..."
        }
      }
    }
  ]
}
```

## Troubleshooting

### First run fails with "Keys were generated"

This is expected behavior:
1. KKeys not found error

If the workflow fails with "Cryptographic keys not found":
1. Clone your repository locally
2. Run: `php .github/scripts/fair/generate-keys-local.php`
3. Copy the displayed keys to GitHub Secrets
4. Re-run the workflow

### First run fails with "Keys were generated"

This error should no longer occur - keys must be generated locally for security.
If the workflow can't find `FAIR_DID`:
- This is normal on first run
- A new DID will be created automatically
- Future runs will use the stored DID

### Signature verification fails

Check that:
- Verification keys haven't been modified
- The workflow has completed successfully
- Artifact hasn't been modified after signing

### Missing plugin file error

Ensure:
- Your plugin has a main PHP file
- The file contains standard WordPress plugin headers
- The file includes `Plugin Name:` header

## File Structure

```
.github/
├── scripts/fair/          # PHP scripts for FAIR operations
│   ├── manage-keys.php    # Key generation and management
│   ├── create-did.php     # DID creation and registration
│   ├── sign-artifact.php  # Package signing
│   ├── generate-metadata.php  # Metadata generation
│   └── update-did-service.php # DID service updates
└── workflows/
    ├── fair-publish.yml   # Main FAIR publishing workflow
    └── fair-release.yml        # GitHub release creation
```

## Security Considerations

### Private Keys
- Never commit private keys to version control
- Keys are stored as encrypted GitHub secrets
- Only accessible to workflows with appropriate permissions

### Key Rotation
If you need to rotate keys:
1. Delete the existing key secrets from repository settings
2. Re-run the workflow to generate new keys
3. Update your DID with new keys (handled automatically)

### Package Verification
Users can verify package integrity:
1. Check the signature against your verification key
2. Verify checksum of downloaded artifact
3. Validate DID authenticity via PLC directory

## Resources

- [FAIR Protocol Specification](https://github.com/fairpm/fair-protocol)
- [FAIR Repository Implementation Guide](https://github.com/fairpm/fair-protocol/blob/main/docs/implementing/repository.md)
- [PLC Directory](https://plc.directory/)
- [DID Manager Library](https://github.com/fairpm/did-manager)

## Support

For issues or questions:
- Check the workflow logs in the Actions tab
- Review the [FAIR Protocol documentation](https://github.com/fairpm/fair-protocol)
- Open an issue in your repository

## License

This workflow implementation follows the same license as your plugin project.
