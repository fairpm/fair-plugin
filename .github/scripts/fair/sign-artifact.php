#!/usr/bin/env php
<?php
/**
 * FAIR Artifact Signing Script
 *
 * Signs a package artifact using the verification key.
 *
 * Required environment variables:
 * - VERIFICATION_PRIVATE
 * - VERIFICATION_PUBLIC
 * - ARTIFACT_PATH
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

if (!file_exists($autoloadPath)) {
    echo "::error::Autoloader not found at {$autoloadPath}\n";
    exit(1);
}

require_once $autoloadPath;

use FAIR\DID\Crypto\DidCodec;
use FAIR\DID\Keys\EdDsaKey;
use FAIR\DID\PLC\PlcOperation;

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
$verificationPrivate = getenv('VERIFICATION_PRIVATE');
$verificationPublic = getenv('VERIFICATION_PUBLIC');
$artifactPath = getenv('ARTIFACT_PATH');

// Validate required inputs
if (empty($verificationPrivate) || empty($verificationPublic)) {
    echo "::error::Verification keys are required\n";
    exit(1);
}

if (empty($artifactPath) || !file_exists($artifactPath)) {
    echo "::error::Artifact path is invalid or file does not exist: {$artifactPath}\n";
    exit(1);
}

// Reconstruct verification key from private key
$verificationKey = EdDsaKey::from_private($verificationPrivate);

// Read artifact and compute hash (as hex string, not binary)
$artifactContents = file_get_contents($artifactPath);
$hash = hash('sha384', $artifactContents, false);  // false = hex output

// Sign the hash (returns hex signature)
$signatureHex = $verificationKey->sign($hash);

// Convert signature to base64url format (hex -> binary -> base64url)
$signatureBinary = hex2bin($signatureHex);
$signature = PlcOperation::base64url_encode($signatureBinary);

// Also compute checksum for metadata
$checksum = hash('sha256', $artifactContents);

echo "::notice::Package signed successfully\n";
write_output('signature', $signature);
write_output('checksum', "sha256:{$checksum}");
