#!/usr/bin/env php
<?php
/**
 * FAIR DID Creation Script
 *
 * Creates or uses an existing PLC DID for the package.
 *
 * Required environment variables:
 * - ROTATION_PRIVATE
 * - ROTATION_PUBLIC
 * - VERIFICATION_PRIVATE
 * - VERIFICATION_PUBLIC
 * - EXISTING_DID (optional)
 * - DID_EXISTS
 * - REPO_URL
 * - GITHUB_OUTPUT (for GitHub Actions output)
 *
 * @package MinimalAdmin
 */

declare(strict_types=1);

// Autoload path - did-manager is cloned to /tmp/did-manager in GitHub Actions
$autoloadPath = '/tmp/did-manager/vendor/autoload.php';

if (!file_exists($autoloadPath)) {
    echo "::error::Autoloader not found at {$autoloadPath}\n";
    exit(1);
}

require_once $autoloadPath;

use FAIR\DID\Crypto\DidCodec;
use FAIR\DID\PLC\PlcClient;
use FAIR\DID\PLC\PlcOperation;
use FAIR\DID\Keys\EcKey;
use FAIR\DID\Keys\EdDsaKey;

/**
 * Write output to GitHub Actions output file.
 *
 * @param string $name  Output name.
 * @param string $value Output value.
 */
function write_output(string $name, string $value): void {
    $outputFile = getenv('GITHUB_OUTPUT');
    if (!$outputFile) {
        return;
    }
    file_put_contents($outputFile, "{$name}={$value}\n", FILE_APPEND);
}

// Get environment variables
$rotationPrivate = getenv('ROTATION_PRIVATE');
$rotationPublic = getenv('ROTATION_PUBLIC');
$verificationPrivate = getenv('VERIFICATION_PRIVATE');
$verificationPublic = getenv('VERIFICATION_PUBLIC');
$existingDid = getenv('EXISTING_DID');
$didExists = getenv('DID_EXISTS') === 'true';
$repoUrl = getenv('REPO_URL');

// Validate required inputs
if (empty($rotationPrivate) || empty($rotationPublic)) {
    echo "::error::Rotation keys are required\n";
    exit(1);
}

if (empty($verificationPrivate) || empty($verificationPublic)) {
    echo "::error::Verification keys are required\n";
    exit(1);
}

// Reconstruct keys from stored private keys
$rotationKey = EcKey::from_private($rotationPrivate);
$verificationKey = EdDsaKey::from_private($verificationPrivate);

if ($didExists && !empty($existingDid)) {
    echo "::notice::Using existing DID: {$existingDid}\n";
    write_output('did', $existingDid);
    write_output('created', 'false');
} else {
    echo "::notice::Creating new PLC DID...\n";

    // Create handle from repository name
    $handle = basename($repoUrl);

    // Create PLC operation for genesis
    $operation = DidCodec::create_plc_operation(
        $rotationKey,
        $verificationKey,
        $handle
    );

    // Sign the operation
    $signedOperation = DidCodec::sign_plc_operation($operation, $rotationKey);

    // Generate DID from signed operation
    $did = DidCodec::generate_plc_did($signedOperation);
    $cid = $signedOperation->get_cid();

    echo "::notice::Generated DID: {$did}\n";
    echo "::notice::Operation CID: {$cid}\n";

    // Add to step summary for easy visibility
    $summaryFile = getenv('GITHUB_STEP_SUMMARY');
    if ($summaryFile) {
        $summary = "\n## DID Generated\n\n";
        $summary .= "Your plugin's DID has been created:\n\n";
        $summary .= "```\n{$did}\n```\n\n";
        $summary .= "### Action Required\n\n";
        $summary .= "You must save this DID as a repository **variable** (not secret):\n\n";
        $summary .= "1. Go to: **Settings** → **Secrets and variables** → **Actions** → **Variables** tab\n";
        $summary .= "2. Click **New repository variable**\n";
        $summary .= "3. Name: `FAIR_DID`\n";
        $summary .= "4. Value: `{$did}`\n";
        $summary .= "5. Click **Add variable**\n\n";
        $summary .= "This is only needed once. Future publishes will use this stored DID.\n";
        file_put_contents($summaryFile, $summary, FILE_APPEND);
    }

    echo "\n";
    echo "╔═══════════════════════════════════════════════════════════════════╗\n";
    echo "║  ACTION REQUIRED: Save Your DID as a GitHub Variable          ║\n";
    echo "╚═══════════════════════════════════════════════════════════════════╝\n";
    echo "\n";
    echo "Your DID has been created: {$did}\n";
    echo "\n";
    echo "To complete setup and enable future publishes, add this as a VARIABLE:\n";
    echo "\n";
    echo "1. Go to: Settings → Secrets and variables → Actions → Variables tab\n";
    echo "2. Click 'New repository variable'\n";
    echo "3. Name: FAIR_DID\n";
    echo "4. Value: {$did}\n";
    echo "5. Click 'Add variable'\n";
    echo "\n";
    echo "WARNING: Use VARIABLES (not Secrets) - DIDs contain special characters.\n";
    echo "\n";
    echo "This step is only needed once. Future publishes will use this DID.\n";
    echo "\n";

    // Submit to PLC directory
    $client = new PlcClient();
    try {
        $operationArray = (array) $signedOperation->jsonSerialize();
        $response = $client->create_did($did, $operationArray);
        echo "::notice::DID submitted to PLC directory successfully\n";
        if (!empty($response)) {
            echo "::notice::PLC Response: " . json_encode($response) . "\n";
        }
    } catch (Exception $e) {
        echo "::warning::Could not submit to PLC directory: " . $e->getMessage() . "\n";
        echo "::notice::DID can still be used locally\n";
    }

    write_output('did', $did);
    write_output('cid', $cid);
    write_output('created', 'true');
}
