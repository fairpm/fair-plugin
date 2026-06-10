<?php
/**
 * Generate test Ed25519 key fixtures for signature verification tests.
 * Idempotent — skips if keypair already exists.
 *
 * Usage: php tests/fixtures/keys/generate.php
 *
 * Creates (if missing):
 *   tests/fixtures/keys/ed25519-keypair.json   — hex-encoded secret + public key
 *   tests/fixtures/keys/did-doc-signed.json     — DID doc with the public key
 *   tests/fixtures/keys/hello-dolly.sig         — detached signature of hello-dolly.zip
 *   tests/fixtures/keys/release-doc-signed.json — release with signature
 */

declare( strict_types=1 );

require_once __DIR__ . '/../../../vendor/autoload.php';

use FAIR\DID\Crypto\DidCodec;

// Idempotent: skip if fixtures already exist.
$keypair_file = __DIR__ . '/ed25519-keypair.json';
if ( file_exists( $keypair_file ) ) {
	$existing = json_decode( file_get_contents( $keypair_file ), true );
	if ( ! empty( $existing['secret_key_hex'] ) && ! empty( $existing['public_key_multibase'] ) ) {
		echo "Keypair already exists. Skipping generation.\n";
		echo "  To regenerate, delete: {$keypair_file}\n";
		exit( 0 );
	}
}

// ── Generate Ed25519 keypair ───────────────────────────────────────

$keypair = sodium_crypto_sign_keypair();
$secret_key = sodium_crypto_sign_secretkey( $keypair );
$public_key = sodium_crypto_sign_publickey( $keypair );

$secret_hex = bin2hex( $secret_key );
$public_hex = bin2hex( $public_key );
$multibase_pub = DidCodec::to_multibase_key( $public_key, DidCodec::MULTICODEC_ED25519_PUB );

// Save keypair.
file_put_contents(
	__DIR__ . '/ed25519-keypair.json',
	json_encode( [
		'secret_key_hex'   => $secret_hex,
		'public_key_hex'   => $public_hex,
		'public_key_multibase' => $multibase_pub,
	], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n"
);

echo "Keypair saved to ed25519-keypair.json\n";
echo "  Public (multibase): {$multibase_pub}\n";

// ── Create DID document fixture ────────────────────────────────────

$did_doc = [
	'id'                 => 'did:plc:signedtest0000000000000000',
	'alsoKnownAs'       => [ 'fair://signed-test.local/' ],
	'verificationMethod' => [
		[
			'id'                  => 'did:plc:signedtest0000000000000000#fair-signing',
			'type'               => 'Multikey',
			'publicKeyMultibase' => $multibase_pub,
		],
	],
	'service' => [
		[
			'id'              => '#fair-repo',
			'type'            => 'FairPackageManagementRepo',
			'serviceEndpoint' => 'http://mock-server:8080/metadata/did:plc:signedtest0000000000000000',
		],
	],
];

file_put_contents(
	__DIR__ . '/did-doc-signed.json',
	json_encode( $did_doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n"
);

echo "DID doc saved to did-doc-signed.json\n";

// ── Sign hello-dolly.zip ───────────────────────────────────────────

$zip_path = __DIR__ . '/../zips/hello-dolly.zip';
if ( ! file_exists( $zip_path ) ) {
	echo "ERROR: hello-dolly.zip not found at {$zip_path}\n";
	exit( 1 );
}

$zip_content = file_get_contents( $zip_path );
$signature = sodium_crypto_sign_detached( $zip_content, $secret_key );

// Save signature as binary file.
file_put_contents( __DIR__ . '/hello-dolly.sig', $signature );

// Also encode as base64url (no-padding) — FAIR protocol format.
$sig_base64url = sodium_bin2base64( $signature, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING );

echo "Signature saved to hello-dolly.sig\n";
echo "  Signature (base64url): {$sig_base64url}\n";

// ── Verify round-trip ──────────────────────────────────────────────

$verified = sodium_crypto_sign_verify_detached( $signature, $zip_content, $public_key );
if ( $verified ) {
	echo "\n✓ Signature round-trip verified.\n";
} else {
	echo "\n✗ SIGNATURE VERIFICATION FAILED!\n";
	exit( 1 );
}

// ── Create release document fixture ────────────────────────────────

$release_doc = [
	'version'    => '1.0.0',
	'artifacts'  => [
		'package' => [
			[
				'url'       => './fixtures/zips/hello-dolly.zip',
				'language'  => 'en_US',
				'signature' => $sig_base64url,
			],
		],
	],
	'files'    => [],
	'requires' => [
		'requires_php' => '8.0',
		'requires_wp'  => '5.4',
	],
];

file_put_contents(
	__DIR__ . '/release-doc-signed.json',
	json_encode( $release_doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n"
);

echo "Release doc saved to release-doc-signed.json\n";
echo "Done.\n";
