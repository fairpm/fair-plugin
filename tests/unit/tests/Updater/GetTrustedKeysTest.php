<?php
/**
 * Tests for FAIR\Updater\get_trusted_keys().
 *
 * @package FAIR
 */

use function FAIR\Updater\get_trusted_keys;
use FAIR\DID\Crypto\DidCodec;
use const FAIR\Packages\CACHE_DID_FOR_INSTALL;
use const FAIR\Packages\CACHE_METADATA_DOCUMENTS;

/**
 * Tests for FAIR\Updater\get_trusted_keys().
 *
 * @covers FAIR\Updater\get_trusted_keys
 */
class GetTrustedKeysTest extends WP_UnitTestCase {

	private string $test_did = 'did:plc:z72i7hdynmk6r22z27h6tvur';

	public function tear_down() {
		delete_site_transient( CACHE_DID_FOR_INSTALL );
		delete_site_transient( CACHE_METADATA_DOCUMENTS . $this->test_did );
		delete_site_transient( CACHE_METADATA_DOCUMENTS . 'did:plc:no-keys' );
		parent::tear_down();
	}

	/**
	 * Test should return empty array when no DID is cached for install.
	 */
	public function test_should_return_empty_when_no_cached_did() {
		delete_site_transient( CACHE_DID_FOR_INSTALL );

		$keys = get_trusted_keys();

		$this->assertIsArray( $keys );
		$this->assertEmpty( $keys, 'Should return empty array when no cached DID.' );
	}

	/**
	 * Test should return empty array when DID document fetch fails.
	 */
	public function test_should_return_empty_when_did_fetch_fails() {
		set_site_transient( CACHE_DID_FOR_INSTALL, 'did:plc:nonexistent', HOUR_IN_SECONDS );

		$keys = get_trusted_keys();

		$this->assertIsArray( $keys );
		$this->assertEmpty( $keys, 'Failed DID fetch should return empty array.' );
	}

	/**
	 * Test should return empty array when DID doc has no signing keys.
	 */
	public function test_should_return_empty_when_no_signing_keys() {
		set_site_transient( CACHE_DID_FOR_INSTALL, 'did:plc:no-keys', HOUR_IN_SECONDS );

		set_site_transient( CACHE_METADATA_DOCUMENTS . 'did:plc:no-keys', [
			'id'                 => 'did:plc:no-keys',
			'verificationMethod' => [
				[
					'id'   => '#other-key',
					'type' => 'Ed25519VerificationKey2018',  // wrong type.
				],
			],
		], HOUR_IN_SECONDS );

		$keys = get_trusted_keys();

		$this->assertIsArray( $keys );
		$this->assertEmpty( $keys, 'No valid Multikey entries should return empty.' );
	}

	/**
	 * Test should return empty array when DID doc has no verificationMethod.
	 */
	public function test_should_return_empty_when_no_verification_method() {
		set_site_transient( CACHE_DID_FOR_INSTALL, $this->test_did, HOUR_IN_SECONDS );

		set_site_transient( CACHE_METADATA_DOCUMENTS . $this->test_did, [
			'id'                 => $this->test_did,
			'verificationMethod' => [],
		], HOUR_IN_SECONDS );

		$keys = get_trusted_keys();

		$this->assertIsArray( $keys );
		$this->assertEmpty( $keys, 'Empty verificationMethod should return empty.' );
	}

	/**
	 * Test should filter out non-fair Multikey entries.
	 */
	public function test_should_filter_out_non_fair_keys() {
		set_site_transient( CACHE_DID_FOR_INSTALL, $this->test_did, HOUR_IN_SECONDS );

		set_site_transient( CACHE_METADATA_DOCUMENTS . $this->test_did, [
			'id'                 => $this->test_did,
			'verificationMethod' => [
				[
					'id'                 => '#atproto-key',
					'type'               => 'Multikey',
					'publicKeyMultibase'  => 'z6MkhaXgBXDvQtf5VgJKe',
				],
			],
		], HOUR_IN_SECONDS );

		$keys = get_trusted_keys();

		// The key has fragment 'atproto', not 'fair', so it should be filtered out.
		$this->assertEmpty( $keys, 'Non-fair keys should be filtered out.' );
	}

	/**
	 * Test that ALL fair-prefixed Multikey entries are returned as trusted keys.
	 *
	 * WordPress core's verify_file_signature() tries every trusted key.
	 * If a DID doc has two #fair-* keys (e.g. primary + backup), both are
	 * returned. A signature from EITHER key will pass verification.
	 * This is intentional: key rotation and backup keys must work.
	 */
	public function test_should_return_all_fair_prefixed_multikeys(): void {
		set_site_transient( CACHE_DID_FOR_INSTALL, $this->test_did, HOUR_IN_SECONDS );

		// Generate two valid Ed25519 keypairs for multibase encoding.
		$kp1 = sodium_crypto_sign_keypair();
		$kp2 = sodium_crypto_sign_keypair();
		$mb1 = \FAIR\DID\Crypto\DidCodec::to_multibase_key(
			sodium_crypto_sign_publickey( $kp1 ),
			\FAIR\DID\Crypto\DidCodec::MULTICODEC_ED25519_PUB
		);
		$mb2 = \FAIR\DID\Crypto\DidCodec::to_multibase_key(
			sodium_crypto_sign_publickey( $kp2 ),
			\FAIR\DID\Crypto\DidCodec::MULTICODEC_ED25519_PUB
		);

		set_site_transient( CACHE_METADATA_DOCUMENTS . $this->test_did, [
			'id'                 => $this->test_did,
			'verificationMethod' => [
				[
					'id'                 => '#fair-signing',
					'type'               => 'Multikey',
					'publicKeyMultibase'  => $mb1,
				],
				[
					'id'                 => '#fair-backup',
					'type'               => 'Multikey',
					'publicKeyMultibase'  => $mb2,
				],
				[
					'id'                 => '#atproto-key',
					'type'               => 'Multikey',
					'publicKeyMultibase'  => 'zQ3shXjHeiBuRCKmM36cuYnm7YEMzhGnCmCyW92sRJ9pribSF',
				],
			],
		], HOUR_IN_SECONDS );

		$keys = get_trusted_keys();

		$this->assertCount( 2, $keys, 'Both fair-prefixed Ed25519 keys should be trusted.' );
	}

	/**
	 * Test that a valid fair-signing key is recoded from multibase (base58btc)
	 * to base64 as expected by WordPress core's verify_file_signature().
	 */
	public function test_should_recode_multibase_key_to_base64(): void {
		set_site_transient( CACHE_DID_FOR_INSTALL, $this->test_did, HOUR_IN_SECONDS );

		// Use a real multibase-encoded Ed25519 public key from fixtures.
		$multibase = 'z6MkfvXWEmEYhoJofpw3akAnthgdGrKgFAgAsBRxQX5MqYh3';

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

		$keys = get_trusted_keys();

		$this->assertCount( 1, $keys, 'Should return one recoded key.' );

		// Verify the recoded key is valid base64.
		$base64_key = $keys[0];
		$decoded = base64_decode( $base64_key, true );
		$this->assertNotFalse( $decoded, 'Recoded key should be valid base64.' );
		$this->assertSame( 32, strlen( $decoded ), 'Decoded key should be 32 bytes (raw Ed25519 public key).' );

		// Verify it matches the expected raw key from DidCodec.
		$expected = DidCodec::from_multibase_key( $multibase );
		$this->assertSame(
			$expected['key'],
			$decoded,
			'base64_decode of recoded key should equal the raw key bytes from from_multibase_key().'
		);
	}
}
