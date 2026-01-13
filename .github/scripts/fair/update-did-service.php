#!/usr/bin/env php
<?php
/**
 * FAIR DID Service Update Script
 *
 * Updates the DID with the FAIR service endpoint.
 *
 * Required environment variables:
 * - DID
 * - ROTATION_PRIVATE
 * - METADATA_URL
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

use FAIR\DID\PLC\PlcClient;
use FAIR\DID\PLC\PlcOperation;
use FAIR\DID\Keys\EcKey;
use FAIR\DID\Keys\KeyFactory;

// Get environment variables
$did = getenv('DID');
$rotationPrivate = getenv('ROTATION_PRIVATE');
$metadataUrl = getenv('METADATA_URL');
$prevCid = getenv('PREV_CID') ?: null;

// Validate required inputs
if (empty($did)) {
    echo "::error::DID is required\n";
    exit(1);
}

if (empty($rotationPrivate)) {
    echo "::error::Rotation private key is required\n";
    exit(1);
}

if (empty($metadataUrl)) {
    echo "::error::Metadata URL is required\n";
    exit(1);
}

// Reconstruct rotation key from private key
echo "::notice::Reconstructing rotation key from private key...\n";
$rotationKey = EcKey::from_private($rotationPrivate);
echo "::notice::Rotation key reconstructed successfully\n";

echo "::notice::Initializing PLC client...\n";
$client = new PlcClient();
echo "::notice::PLC client initialized\n";

try {
    // Get current DID document
    echo "::notice::Fetching current DID document for: {$did}\n";
    $currentDoc = $client->resolve_did($did);
    echo "::notice::DID document retrieved successfully\n";

    // Get the previous CID - use passed value or fetch from PLC directory
    if (!empty($prevCid)) {
        echo "::notice::Using previous CID from create-did step: {$prevCid}\n";
    } else {
        echo "::notice::No previous CID provided, fetching from PLC directory...\n";
        $prevCid = $client->get_previous_cid($did);
        echo "::notice::Previous CID retrieved from PLC: {$prevCid}\n";
    }
    // Decode existing verification methods from rotationKeys (not verificationMethod)
    // The rotationKeys field contains the keys we need to preserve
    echo "::notice::Preserving rotation keys...\n";
    $rotationKeys = [$rotationKey];  // Start with our rotation key

    // Get the verification key ID from the DID document
    // Extract the verification method from current document
    echo "::notice::Extracting verification methods...\n";
    $verificationMethods = [];
    $methodsData = $currentDoc['verificationMethod'] ?? [];
    echo "::notice::Found " . count($methodsData) . " verification methods in current document\n";

    foreach ($methodsData as $method) {
        $methodId = $method['id'] ?? '';
        $publicKeyMultibase = $method['publicKeyMultibase'] ?? '';
        if (!empty($publicKeyMultibase) && !empty($methodId)) {
            echo "::notice::Decoding verification method: {$methodId}\n";
            // Construct full did:key URI from multibase key
            $didKey = 'did:key:' . $publicKeyMultibase;
            // Extract just the fragment (e.g., "#atproto" from "did:plc:xxx#atproto")
            $fragment = substr($methodId, strrpos($methodId, '#') + 1);
            $verificationMethods[$fragment] = KeyFactory::decode_did_key($didKey);
        }
    }
    echo "::notice::Successfully decoded " . count($verificationMethods) . " verification methods\n";

    // Get existing alsoKnownAs
    echo "::notice::Preserving alsoKnownAs...\n";
    $alsoKnownAs = $currentDoc['alsoKnownAs'] ?? [];

    // Build services with FAIR endpoint
    // Following the pattern from 07-generate-and-submit-did.php example
    $services = [
        'fairpm_repo' => [
            'type' => 'FairPackageManagementRepo',
            'endpoint' => $metadataUrl,
        ],
    ];
    echo "::notice::Service endpoint configured\n";

    echo "::group::Update Details\n";
    echo "Rotation Keys: " . count($rotationKeys) . "\n";
    echo "Verification Methods: " . count($verificationMethods) . "\n";
    echo "Also Known As: " . json_encode($alsoKnownAs) . "\n";
    echo "Services: " . json_encode($services, JSON_PRETTY_PRINT) . "\n";
    echo "Previous operation CID: {$prevCid}\n";
    echo "::endgroup::\n";

    // Build update operation following the example pattern
    $operation = new PlcOperation(
        type: 'plc_operation',
        rotation_keys: $rotationKeys,
        verification_methods: $verificationMethods,
        also_known_as: $alsoKnownAs,
        services: $services,
        prev: $prevCid,
    );

    // Sign the operation using DidCodec::sign_plc_operation()
    echo "::notice::Signing operation...\n";
    $signedOp = \FAIR\DID\Crypto\DidCodec::sign_plc_operation($operation, $rotationKey);

    echo "::group::Signed Operation\n";
    echo json_encode((array) $signedOp->jsonSerialize(), JSON_PRETTY_PRINT) . "\n";
    echo "::endgroup::\n";

    // Submit update to PLC directory
    echo "::notice::Submitting update to PLC directory...\n";
    $client->update_did($did, (array) $signedOp->jsonSerialize());

    echo "::notice::DID updated with FAIR service endpoint: {$metadataUrl}\n";

    // Verify the update
    echo "::notice::Verifying update by fetching DID document again...\n";
    $updatedDoc = $client->resolve_did($did);
    echo "::notice::Updated DID document retrieved\n";

    echo "::group::Updated DID Document\n";
    echo json_encode($updatedDoc, JSON_PRETTY_PRINT) . "\n";
    echo "::endgroup::\n";

    $serviceCount = isset($updatedDoc['service']) ? count($updatedDoc['service']) : 0;
    echo "::notice::Services in updated document: {$serviceCount}\n";

    if (isset($updatedDoc['service']) && !empty($updatedDoc['service'])) {
        echo "::notice::Services array updated successfully\n";
        foreach ($updatedDoc['service'] as $service) {
            $serviceId = $service['id'] ?? 'unknown';
            $serviceType = $service['type'] ?? 'unknown';
            echo "::notice::Service found - ID: {$serviceId}, Type: {$serviceType}\n";
        }
    } else {
        echo "::warning::Services array is empty after update\n";
    }
} catch (Exception $e) {
    echo "::error::Could not update DID service: " . $e->getMessage() . "\n";
    exit(1);
}
