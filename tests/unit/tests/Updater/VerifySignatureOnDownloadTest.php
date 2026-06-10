<?php
/**
 * Tests for FAIR\Updater\verify_signature_on_download().
 *
 * Verifies the download-and-verify hook that protects package
 * installations against tampered or unsigned artifacts.
 *
 * @package FAIR
 */

declare( strict_types=1 );

namespace FAIR\Tests\Updater;

use function FAIR\Updater\verify_signature_on_download;
use const FAIR\Packages\CACHE_DID_FOR_INSTALL;
use const FAIR\Packages\CACHE_RELEASE_PACKAGES;
use const FAIR\Packages\CACHE_METADATA_DOCUMENTS;

require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
require_once ABSPATH . 'wp-admin/includes/file.php';

/**
 * Tests for verify_signature_on_download() guard clauses and failure paths.
 *
 * @covers FAIR\Updater\verify_signature_on_download
 */
class VerifySignatureOnDownloadTest extends \WP_UnitTestCase {

	private string $test_did = 'did:plc:z72i7hdynmk6r22z27h6tvur';
	private string $artifact_url = 'https://example.com/releases/test-plugin-1.0.0.zip';

	protected function tearDown(): void {
		delete_site_transient( CACHE_DID_FOR_INSTALL );
		delete_site_transient( CACHE_RELEASE_PACKAGES );
		delete_site_transient( CACHE_METADATA_DOCUMENTS . $this->test_did );
		remove_all_filters( 'wp_trusted_keys' );

		// Reset the $has_run static variable in verify_signature_on_download.
		$ref = new \ReflectionFunction( 'FAIR\Updater\verify_signature_on_download' );
		$static = $ref->getStaticVariables();
		if ( isset( $static['has_run'] ) ) {
			$has_run = &$ref->getStaticVariables()['has_run'];
			$has_run = [];
		}

		parent::tearDown();
	}

	/**
	 * Create a Plugin_Upgrader mock that returns a given path or error from download_package().
	 *
	 * @param string|\WP_Error $download_result What download_package() should return.
	 */
	private function mock_upgrader( $download_result ): \Plugin_Upgrader {
		$result = $download_result;
		return new class( $result ) extends \Plugin_Upgrader {
			/** @var string|\WP_Error */
			private $mockResult;

			public function __construct( $mockResult ) {
				$this->mockResult = $mockResult;
			}

			public function run( $options ) {
				return true;
			}

			public function download_package( $package, $check_signatures = false, $hook_extra = [] ) {
				return $this->mockResult;
			}
		};
	}

	/**
	 * Create a ReleaseDocument with a given signature and artifact URL.
	 */
	private function release_with_signature( string $signature_base64url, string $url = null ): \FAIR\Packages\ReleaseDocument {
		return \FAIR\Packages\ReleaseDocument::from_data( (object) [
			'version'   => '1.0.0',
			'artifacts' => (object) [
				'package' => [
					(object) [
						'url'       => $url ?? $this->artifact_url,
						'signature' => $signature_base64url,
					],
				],
			],
		] );
	}

	/**
	 * Test that a non-false $reply (already downloaded) is returned unchanged.
	 */
	public function test_should_return_reply_when_already_downloaded(): void {
		$result = verify_signature_on_download(
			'/path/to/downloaded.zip',
			$this->artifact_url,
			$this->mock_upgrader( '/path/to/downloaded.zip' ),
			[]
		);

		$this->assertSame( '/path/to/downloaded.zip', $result, 'Non-false reply should pass through.' );
	}

	/**
	 * Test that a non-Plugin/Theme upgrader returns the reply unchanged.
	 */
	public function test_should_return_reply_for_non_plugin_upgrader(): void {
		$generic = new class extends \WP_Upgrader {
			public function run( $options ) {
				return true;
			}
		};

		$result = verify_signature_on_download(
			false,
			$this->artifact_url,
			$generic,
			[]
		);

		$this->assertFalse( $result, 'Non-plugin/theme upgrader should pass through.' );
	}

	/**
	 * Test that missing CACHE_DID_FOR_INSTALL returns reply unchanged.
	 */
	public function test_should_return_reply_when_no_cached_did(): void {
		delete_site_transient( CACHE_DID_FOR_INSTALL );

		$result = verify_signature_on_download(
			false,
			$this->artifact_url,
			$this->mock_upgrader( '/tmp/file.zip' ),
			[]
		);

		$this->assertFalse( $result, 'Missing DID transient should return false unchanged.' );
	}

	/**
	 * Test that missing release in CACHE_RELEASE_PACKAGES returns reply unchanged.
	 */
	public function test_should_return_reply_when_no_cached_release(): void {
		set_site_transient( CACHE_DID_FOR_INSTALL, $this->test_did, HOUR_IN_SECONDS );
		delete_site_transient( CACHE_RELEASE_PACKAGES );

		$result = verify_signature_on_download(
			false,
			$this->artifact_url,
			$this->mock_upgrader( '/tmp/file.zip' ),
			[]
		);

		$this->assertFalse( $result, 'Missing release cache should return false unchanged.' );
	}

	/**
	 * Test that a local file is returned untouched (no download, no verification).
	 */
	public function test_should_return_local_file_untouched(): void {
		set_site_transient( CACHE_DID_FOR_INSTALL, $this->test_did, HOUR_IN_SECONDS );

		$zip = sys_get_temp_dir() . '/fair-test-local.zip';
		file_put_contents( $zip, 'dummy zip content' );

		$result = verify_signature_on_download(
			false,
			$zip,
			$this->mock_upgrader( $zip ),
			[]
		);

		$this->assertSame( $zip, $result, 'Local files should pass through without verification.' );

		unlink( $zip );
	}

	/**
	 * Test that $has_run guard prevents re-entry for the same DID + URL.
	 * Uses throwaway DID/URL to avoid interfering with other tests.
	 */
	public function test_should_prevent_re_entry_for_same_package(): void {
		$run_did = 'did:plc:has-run-test';
		$run_url = 'https://unique.example.com/release.zip';

		// Create a real file so the first call doesn't crash on hash_file().
		$tmp_file = sys_get_temp_dir() . '/fair-has-run-test.zip';
		file_put_contents( $tmp_file, "PK\x03\x04" . str_repeat( 'x', 100 ) );

		set_site_transient( CACHE_DID_FOR_INSTALL, $run_did, HOUR_IN_SECONDS );

		// Seed a release with a structurally-valid but wrong signature.
		// Must be valid base64url to pass sodium_base642bin().
		$dummy_sig = sodium_bin2base64(
			str_repeat( "\x00", SODIUM_CRYPTO_SIGN_BYTES ),
			SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING
		);

		set_site_transient( CACHE_RELEASE_PACKAGES, [
			$run_did => $this->release_with_signature( $dummy_sig, $run_url ),
		] );

		// First call — enters verification, sets $has_run.
		@verify_signature_on_download(
			false,
			$run_url,
			$this->mock_upgrader( $tmp_file ),
			[]
		);

		// Second call with same args should short-circuit via $has_run,
		// returning the original reply (false) without calling download_package.
		$second = verify_signature_on_download(
			false,
			$run_url,
			$this->mock_upgrader( $tmp_file ),
			[]
		);

		$this->assertFalse( $second, '$has_run guard blocks re-entry, returns original false reply.' );

		// Clean up.
		delete_site_transient( CACHE_DID_FOR_INSTALL );
		delete_site_transient( CACHE_RELEASE_PACKAGES );
		@unlink( $tmp_file );
	}

	/**
	 * Test that a WP_Error from download_package() is propagated.
	 */
	public function test_should_propagate_download_error(): void {
		set_site_transient( CACHE_DID_FOR_INSTALL, $this->test_did, HOUR_IN_SECONDS );
		set_site_transient( CACHE_RELEASE_PACKAGES, [
			$this->test_did => $this->release_with_signature( 'any_sig', $this->artifact_url ),
		] );

		$error = new \WP_Error( 'download_failed', 'Simulated download failure.' );

		$result = verify_signature_on_download(
			false,
			$this->artifact_url,
			$this->mock_upgrader( $error ),
			[]
		);

		$this->assertWPError( $result, 'Download error should propagate.' );
		$this->assertSame( 'download_failed', $result->get_error_code() );
	}

	/**
	 * Test that when package URL doesn't match any release artifact, reply passes through.
	 */
	public function test_should_return_reply_when_url_not_in_release(): void {
		set_site_transient( CACHE_DID_FOR_INSTALL, $this->test_did, HOUR_IN_SECONDS );
		set_site_transient( CACHE_RELEASE_PACKAGES, [
			$this->test_did => $this->release_with_signature( 'any_sig', 'https://other.example.com/other.zip' ),
		] );

		$result = verify_signature_on_download(
			false,
			$this->artifact_url, // Different URL than what's in the release.
			$this->mock_upgrader( '/tmp/file.zip' ),
			[]
		);

		$this->assertFalse( $result, 'Unmatched URL should pass through unchanged.' );
	}

	/**
	 * Test that a valid Ed25519 signature passes verification.
	 *
	 * This is the full security-critical path: DID → key → sign → verify.
	 */
	public function test_valid_signature_passes_verification(): void {
		// Generate a fresh Ed25519 keypair.
		$keypair = sodium_crypto_sign_keypair();
		$secret_key = sodium_crypto_sign_secretkey( $keypair );
		$public_key = sodium_crypto_sign_publickey( $keypair );

		// Create a real zip file to sign.
		$zip_path = sys_get_temp_dir() . '/fair-sig-test-' . uniqid() . '.zip';
		file_put_contents( $zip_path, "PK\x03\x04" . str_repeat( 'x', 200 ) ); // Minimal zip.

		// Sign the file. WP 6.4+ uses SHA-384 hash for verify_file_signature().
		$content_to_sign = hash_file( 'sha384', $zip_path, true );
		$signature_raw = sodium_crypto_sign_detached( $content_to_sign, $secret_key );

		// Encode signature as base64url no-padding (FAIR format).
		$signature_base64url = sodium_bin2base64( $signature_raw, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING );

		// Seed DID document with the public key (multibase-encoded).
		$multibase = 'z' . "\xed\x01" . $public_key; // Raw: z + multicodec prefix + key bytes.
		$multibase = \FAIR\DID\Crypto\DidCodec::to_multibase_key( $public_key, \FAIR\DID\Crypto\DidCodec::MULTICODEC_ED25519_PUB );

		set_site_transient( CACHE_METADATA_DOCUMENTS . $this->test_did, [
			'id'                 => $this->test_did,
			'verificationMethod' => [
				[
					'id'                 => '#fair-signing',
					'type'               => 'Multikey',
					'publicKeyMultibase'  => $multibase,
				],
			],
		], HOUR_IN_SECONDS );

		// Seed the install DID + release cache.
		set_site_transient( CACHE_DID_FOR_INSTALL, $this->test_did, HOUR_IN_SECONDS );
		set_site_transient( CACHE_RELEASE_PACKAGES, [
			$this->test_did => $this->release_with_signature( $signature_base64url, $this->artifact_url ),
		] );

		$result = verify_signature_on_download(
			false,
			$this->artifact_url,
			$this->mock_upgrader( $zip_path ), // The upgrader "downloads" our signed zip.
			[]
		);

		$this->assertSame( $zip_path, $result, 'Valid signature should return the downloaded path.' );

		// Clean up.
		@unlink( $zip_path );
	}

	/**
	 * Test that a tampered file fails verification.
	 */
	public function test_tampered_file_fails_verification(): void {
		$keypair = sodium_crypto_sign_keypair();
		$secret_key = sodium_crypto_sign_secretkey( $keypair );
		$public_key = sodium_crypto_sign_publickey( $keypair );

		// Create original zip, sign it, then tamper.
		$zip_path = sys_get_temp_dir() . '/fair-sig-test-' . uniqid() . '.zip';
		file_put_contents( $zip_path, "PK\x03\x04" . str_repeat( 'x', 200 ) );

		$content_to_sign = hash_file( 'sha384', $zip_path, true );
		$signature_raw = sodium_crypto_sign_detached( $content_to_sign, $secret_key );
		$signature_base64url = sodium_bin2base64( $signature_raw, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING );

		// Tamper with the file after signing.
		file_put_contents( $zip_path, 'tampered content — not the original zip' );

		$multibase = \FAIR\DID\Crypto\DidCodec::to_multibase_key( $public_key, \FAIR\DID\Crypto\DidCodec::MULTICODEC_ED25519_PUB );

		set_site_transient( CACHE_METADATA_DOCUMENTS . $this->test_did, [
			'id'                 => $this->test_did,
			'verificationMethod' => [
				[
					'id'                 => '#fair-signing',
					'type'               => 'Multikey',
					'publicKeyMultibase'  => $multibase,
				],
			],
		], HOUR_IN_SECONDS );

		set_site_transient( CACHE_DID_FOR_INSTALL, $this->test_did, HOUR_IN_SECONDS );
		set_site_transient( CACHE_RELEASE_PACKAGES, [
			$this->test_did => $this->release_with_signature( $signature_base64url, $this->artifact_url ),
		] );

		$result = verify_signature_on_download(
			false,
			$this->artifact_url,
			$this->mock_upgrader( $zip_path ),
			[]
		);

		$this->assertWPError( $result, 'Tampered file should fail verification.' );

		@unlink( $zip_path );
	}
}
