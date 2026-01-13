#!/usr/bin/env php
<?php
/**
 * FAIR Key Management Script
 *
 * Checks for existing cryptographic keys in environment variables.
 * If keys don't exist, generates new secp256k1 (rotation) and Ed25519 (verification) key pairs.
 *
 * Required environment variables:
 * - FAIR_ROTATION_KEY_PRIVATE
 * - FAIR_ROTATION_KEY_PUBLIC
 * - FAIR_VERIFICATION_KEY_PRIVATE
 * - FAIR_VERIFICATION_KEY_PUBLIC
 * - FAIR_DID (optional)
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
use FAIR\DID\Keys\EcKey;
use FAIR\DID\Keys\EdDsaKey;

/**
 * Write output to GitHub Actions output file.
 *
 * @param string $name  Output name.
 * @param string $value Output value.
 * @param bool   $multiline Whether the value is multiline.
 */
function write_output(string $name, string $value, bool $multiline = false): void {
    $outputFile = getenv('GITHUB_OUTPUT');
    if (!$outputFile) {
        return;
    }

    if ($multiline) {
        file_put_contents($outputFile, "{$name}<<EOF\n{$value}\nEOF\n", FILE_APPEND);
    } else {
        file_put_contents($outputFile, "{$name}={$value}\n", FILE_APPEND);
    }
}

// Get environment variables
$rotationPrivate = getenv('FAIR_ROTATION_KEY_PRIVATE');
$rotationPublic = getenv('FAIR_ROTATION_KEY_PUBLIC');
$verificationPrivate = getenv('FAIR_VERIFICATION_KEY_PRIVATE');
$verificationPublic = getenv('FAIR_VERIFICATION_KEY_PUBLIC');
// Get existing DID from repository variables (not secrets)
$existingDid = getenv('FAIR_DID');

$keysExist = !empty($rotationPrivate) && !empty($rotationPublic)
          && !empty($verificationPrivate) && !empty($verificationPublic);

if ($keysExist) {
    echo "::notice::Using existing keys from secrets\n";

    // Output existing keys for use in subsequent steps
    write_output('keys_exist', 'true');
    write_output('rotation_private', $rotationPrivate, true);
    write_output('rotation_public', $rotationPublic, true);
    write_output('verification_private', $verificationPrivate, true);
    write_output('verification_public', $verificationPublic, true);

    if (!empty($existingDid)) {
        write_output('did', $existingDid);
        write_output('did_exists', 'true');
    } else {
        write_output('did_exists', 'false');
    }
} else {
    echo "::error::Cryptographic keys not found in repository secrets.\n";
    echo "::error::For security, keys must be generated on your LOCAL machine.\n";
    echo "::error::\n";
    echo "::error::INSTRUCTIONS:\n";
    echo "::error::1. Clone this repository to your local machine\n";
    echo "::error::2. Run: php .github/scripts/fair/generate-keys-local.php\n";
    echo "::error::3. Copy the generated keys to GitHub Secrets\n";
    echo "::error::4. Re-run this workflow\n";
    echo "::error::\n";
    echo "::error::Keys are never generated in GitHub Actions for security.\n";

    write_output('keys_exist', 'false');
    exit(1);
}
