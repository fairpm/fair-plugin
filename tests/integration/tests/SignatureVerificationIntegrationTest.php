<?php
/**
 * Integration tests for Ed25519 signature verification via WordPress.
 *
 * Tests the full pipeline: DID document → trusted keys → verify_file_signature().
 * Uses real Ed25519 keys generated at test time (avoids libsodium version format
 * differences between PHP 8.0 and PHP 8.2+).
 *
 * These tests require WordPress (verify_file_signature is a WP 5.2+ function)
 * and run inside the Docker integration container.
 *
 * @package FAIR\Tests\Integration
 */

declare( strict_types=1 );

namespace FAIR\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * @group signature
 */
class SignatureVerificationIntegrationTest extends TestCase {

	/**
	 * Path to the zip file used as signed artifact.
	 */
	private string $zip_path;

	/**
	 * Base64-encoded public key (32 raw bytes, base64).
	 */
	private string $trusted_key_base64;

	/**
	 * Base64-encoded detached Ed25519 signature over SHA-512 of the zip file.
	 */
	private string $signature_base64;

	protected function setUp(): void {
		parent::setUp();

		// Paths assume the container mounts the plugin at:
		// /var/www/html/wp-content/plugins/fair-plugin/
		$this->zip_path = WP_PLUGIN_DIR . '/fair-plugin/tests/fixtures/zips/hello-dolly.zip';

		if ( ! file_exists( $this->zip_path ) ) {
			$this->markTestSkipped( 'hello-dolly.zip fixture missing.' );
		}

		// WordPress 6.4+ verify_file_signature() signs the SHA-512 hash
		// of the file content (not the raw content).  Generate a fresh
		// keypair and sign the hash.
		$keypair    = sodium_crypto_sign_keypair();
		$secret_key = sodium_crypto_sign_secretkey( $keypair );
		$this->trusted_key_base64 = base64_encode( sodium_crypto_sign_publickey( $keypair ) );

		$file_hash = hash_file( 'sha384', $this->zip_path, true );
		$this->signature_base64 = base64_encode(
			sodium_crypto_sign_detached( $file_hash, $secret_key )
		);
	}

	/**
	 * Test verify_file_signature succeeds with correct key + signature.
	 */
	public function test_verify_file_signature_succeeds(): void {
		$this->assertFileExists( $this->zip_path );

		// Register the trusted key temporarily.
		$trusted_key = $this->trusted_key_base64;
		add_filter( 'wp_trusted_keys', static function ( array $keys ) use ( $trusted_key ): array {
			$keys[] = $trusted_key;
			return $keys;
		}, 100 );

		$result = \verify_file_signature( $this->zip_path, $this->signature_base64 );

		remove_all_filters( 'wp_trusted_keys' );

		$this->assertTrue( $result, 'verify_file_signature should return true for a valid signature.' );
	}

	/**
	 * Test verify_file_signature fails with a tampered file.
	 */
	public function test_verify_file_signature_fails_for_tampered_file(): void {
		// Create a file that is definitely not the signed ZIP.
		$tampered = tempnam( sys_get_temp_dir(), 'fair-tampered-' );
		file_put_contents( $tampered, 'this is not a valid zip file' );

		$trusted_key = $this->trusted_key_base64;
		add_filter( 'wp_trusted_keys', static function ( array $keys ) use ( $trusted_key ): array {
			$keys[] = $trusted_key;
			return $keys;
		}, 100 );

		$result = \verify_file_signature( $tampered, $this->signature_base64 );

		remove_all_filters( 'wp_trusted_keys' );
		unlink( $tampered );

		$this->assertNotTrue( $result, 'verify_file_signature should fail for tampered content.' );
	}

	/**
	 * Test verify_file_signature fails when the key is not trusted.
	 */
	public function test_verify_file_signature_fails_without_trusted_key(): void {
		$this->assertFileExists( $this->zip_path );

		// wp_trusted_keys filter not configured → no trusted keys.
		$result = \verify_file_signature( $this->zip_path, $this->signature_base64 );

		$this->assertNotTrue( $result, 'verify_file_signature should fail when no trusted key matches.' );
	}

	/**
	 * Test verify_file_signature fails with wrong signature + right key.
	 */
	public function test_verify_file_signature_fails_with_wrong_signature(): void {
		$this->assertFileExists( $this->zip_path );

		// Generate a signature from a different keypair.
		$other_keypair = sodium_crypto_sign_keypair();
		$other_secret  = sodium_crypto_sign_secretkey( $other_keypair );
		$wrong_sig_raw = sodium_crypto_sign_detached(
			hash_file( 'sha384', $this->zip_path, true ),
			$other_secret
		);
		$wrong_sig_b64 = base64_encode( $wrong_sig_raw );

		$trusted_key = $this->trusted_key_base64;
		add_filter( 'wp_trusted_keys', static function ( array $keys ) use ( $trusted_key ): array {
			$keys[] = $trusted_key;
			return $keys;
		}, 100 );

		$result = \verify_file_signature( $this->zip_path, $wrong_sig_b64 );

		remove_all_filters( 'wp_trusted_keys' );

		$this->assertNotTrue( $result, 'verify_file_signature should fail for an incorrect signature.' );
	}

	/**
	 * Test that the base64 key format is consistent across the pipeline.
	 *
	 * This verifies the key encoding path: sodium keypair → base64 →
	 * wp_trusted_keys filter → verify_file_signature.
	 */
	public function test_key_encoding_round_trip(): void {
		// The trusted key should be a valid base64 string.
		$this->assertMatchesRegularExpression( '/^[A-Za-z0-9+\/=]+$/', $this->trusted_key_base64 );

		// Decoding back should give 32 bytes (Ed25519 public key).
		$roundtrip = base64_decode( $this->trusted_key_base64, true );
		$this->assertNotFalse( $roundtrip );
		$this->assertSame( SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES, strlen( $roundtrip ) );
	}
}
