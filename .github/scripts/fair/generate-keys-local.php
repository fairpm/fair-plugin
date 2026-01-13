#!/usr/bin/env php
<?php
/**
 * FAIR Key Generation Script - Run Locally
 *
 * This script generates cryptographic keys on YOUR LOCAL MACHINE for security.
 * Keys never leave your computer and are never uploaded to GitHub.
 *
 * Usage:
 *   php generate-keys-local.php
 *
 * @package MinimalAdmin
 */

declare(strict_types=1);

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║  FAIR Cryptographic Key Generator (Local)                 ║\n";
echo "║  Keys are generated on YOUR machine for security          ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "\n";

// Check if running in GitHub Actions
if (getenv('GITHUB_ACTIONS') === 'true') {
    echo "ERROR: This script should NOT be run in GitHub Actions!\n";
    echo "   Run it locally on your machine instead.\n\n";
    exit(1);
}

// Check for composer dependencies
if (!file_exists(__DIR__ . '/../../../vendor/autoload.php')) {
    echo "ERROR: Dependencies not installed.\n";
    echo "   Please run: composer install\n\n";
    exit(1);
}

require_once __DIR__ . '/../../../vendor/autoload.php';

// Try to load FAIR DID Manager (if available locally)
$didManagerPath = '/tmp/did-manager/vendor/autoload.php';
if (file_exists($didManagerPath)) {
    require_once $didManagerPath;
} else {
    echo "WARNING: FAIR DID Manager not found locally.\n";
    echo "   Cloning from GitHub...\n\n";

    $cloneCmd = 'git clone --branch initial-implementation --depth 1 https://github.com/fairpm/did-manager.git /tmp/did-manager 2>&1';
    exec($cloneCmd, $output, $returnCode);

    if ($returnCode !== 0) {
        echo "ERROR: Failed to clone DID Manager.\n";
        echo "   Please install it manually or check your internet connection.\n\n";
        exit(1);
    }

    echo "   Installing dependencies...\n";
    exec('cd /tmp/did-manager && composer install --no-dev --prefer-dist --no-progress 2>&1', $output, $returnCode);

    if ($returnCode !== 0) {
        echo "ERROR: Failed to install dependencies.\n\n";
        exit(1);
    }

    require_once $didManagerPath;
}

use FAIR\DID\Crypto\DidCodec;
use FAIR\DID\Keys\EcKey;
use FAIR\DID\Keys\EdDsaKey;

echo "🔐 Generating cryptographic keys...\n\n";

try {
    // Generate secp256k1 key pair for rotation
    $rotationKey = DidCodec::generate_key_pair();

    // Generate Ed25519 key pair for verification
    $verificationKey = DidCodec::generate_ed25519_key_pair();

    $rotationPrivate = $rotationKey->encode_private();
    $rotationPublic = $rotationKey->encode_public();
    $verificationPrivate = $verificationKey->encode_private();
    $verificationPublic = $verificationKey->encode_public();

    echo "Keys generated successfully!\n\n";

} catch (Exception $e) {
    echo "ERROR: Failed to generate keys.\n";
    echo "   {$e->getMessage()}\n\n";
    exit(1);
}

// Display keys for user to copy
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║  📋 COPY THESE KEYS TO YOUR GITHUB REPOSITORY SECRETS     ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "\n";

echo "1. Go to your repository on GitHub\n";
echo "2. Navigate to: Settings → Secrets and variables → Actions\n";
echo "3. Click 'New repository secret' for each key below:\n\n";

echo "─────────────────────────────────────────────────────────────\n";
echo "SECRET NAME: FAIR_ROTATION_KEY_PRIVATE\n";
echo "─────────────────────────────────────────────────────────────\n";
echo $rotationPrivate . "\n\n";

echo "─────────────────────────────────────────────────────────────\n";
echo "SECRET NAME: FAIR_ROTATION_KEY_PUBLIC\n";
echo "─────────────────────────────────────────────────────────────\n";
echo $rotationPublic . "\n\n";

echo "─────────────────────────────────────────────────────────────\n";
echo "SECRET NAME: FAIR_VERIFICATION_KEY_PRIVATE\n";
echo "─────────────────────────────────────────────────────────────\n";
echo $verificationPrivate . "\n\n";

echo "─────────────────────────────────────────────────────────────\n";
echo "SECRET NAME: FAIR_VERIFICATION_KEY_PUBLIC\n";
echo "─────────────────────────────────────────────────────────────\n";
echo $verificationPublic . "\n\n";

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║  IMPORTANT SECURITY NOTES                                  ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "• These keys were generated on YOUR local machine\n";
echo "• They were NEVER uploaded to GitHub or any server\n";
echo "• Keep the PRIVATE keys secure - never share them\n";
echo "• After copying to GitHub Secrets, clear your terminal history\n";
echo "• You can delete /tmp/did-manager after setup\n";
echo "\n";
echo "Once all secrets are added, run the workflow on GitHub!\n";
echo "\n";
