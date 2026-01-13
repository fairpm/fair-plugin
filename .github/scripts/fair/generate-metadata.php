#!/usr/bin/env php
<?php
/**
 * FAIR Metadata Generation Script
 *
 * Generates FAIR-compliant metadata.json from plugin headers and readme.txt.
 *
 * Required environment variables:
 * - DID
 * - VERSION
 * - CHECKSUM
 * - SIGNATURE
 * - ARTIFACT_PATH
 * - VERIFICATION_PUBLIC
 * - REPO_URL
 * - GITHUB_WORKSPACE
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

use FAIR\DID\Parsers\PluginHeaderParser;
use FAIR\DID\Parsers\ReadmeParser;
use FAIR\DID\Parsers\MetadataGenerator;

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
$did = getenv('DID');
$version = getenv('VERSION');
$checksum = getenv('CHECKSUM');
$signature = getenv('SIGNATURE');
$artifactPath = getenv('ARTIFACT_PATH');
$verificationPublic = getenv('VERIFICATION_PUBLIC');
$repoUrl = getenv('REPO_URL');
$workDir = getenv('GITHUB_WORKSPACE');

// Validate required inputs
if (empty($did)) {
    echo "::error::DID is required\n";
    exit(1);
}

if (empty($version)) {
    echo "::error::Version is required\n";
    exit(1);
}

// Find main plugin file
$pluginFiles = glob($workDir . '/*.php');
$mainPluginFile = null;
foreach ($pluginFiles as $file) {
    $contents = file_get_contents($file);
    if (strpos($contents, 'Plugin Name:') !== false) {
        $mainPluginFile = $file;
        break;
    }
}

if (!$mainPluginFile) {
    echo "::error::Could not find main plugin file\n";
    exit(1);
}

// Parse plugin header
$headerParser = new PluginHeaderParser();
$headerData = $headerParser->parse_file($mainPluginFile);

// Parse readme.txt if exists
$readmeData = [];
$readmePath = $workDir . '/readme.txt';
if (file_exists($readmePath)) {
    $readmeParser = new ReadmeParser();
    $readmeData = $readmeParser->parse_file($readmePath);
}

// Generate FAIR metadata
$generator = new MetadataGenerator($headerData, $readmeData);
$generator->set_did($did);

// Get artifact filename
$artifactFilename = basename($artifactPath);

// Build release URL (GitHub release asset)
$releaseUrl = $repoUrl . "/releases/download/{$version}/{$artifactFilename}";

// Generate metadata with release info
$metadata = $generator->generate();

// Add/override release information
$metadata['releases'] = [
    [
        'version' => ltrim($version, 'v'),
        'requires' => [
            'env:php' => '>=' . ($headerData['RequiresPHP'] ?? '7.4'),
            'env:wp' => '>=' . ($headerData['RequiresAtLeast'] ?? '6.0'),
        ],
        'artifacts' => [
            'package' => [
				[
					'id' => 'main',
					'url' => $releaseUrl,
					'content-type' => 'application/zip',
					'signature' => $signature,
					'checksum' => $checksum,
				]
            ],
        ],
    ],
];

// Ensure required fields
if (!isset($metadata['@context'])) {
    $metadata['@context'] = 'https://fair.pm/ns/metadata/v1';
}
if (!isset($metadata['id'])) {
    $metadata['id'] = $did;
}
if (!isset($metadata['type'])) {
    $metadata['type'] = 'wp-plugin';
}

// Write metadata to file
$metadataPath = '/tmp/fair-metadata.json';
file_put_contents($metadataPath, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "::notice::FAIR metadata generated successfully\n";
echo json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

write_output('metadata_path', $metadataPath);
