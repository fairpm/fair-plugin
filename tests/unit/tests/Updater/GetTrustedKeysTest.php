<?php
/**
 * Tests for FAIR\Updater\get_trusted_keys().
 *
 * @package FAIR
 */

use function FAIR\Updater\get_trusted_keys;
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
}
