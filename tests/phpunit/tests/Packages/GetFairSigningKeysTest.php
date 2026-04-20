<?php
/**
 * Tests for FAIR\Packages\get_fair_signing_keys().
 *
 * @package FAIR
 */

use function FAIR\Packages\get_fair_signing_keys;

/**
 * Tests for FAIR\Packages\get_fair_signing_keys().
 *
 * @covers FAIR\Packages\get_fair_signing_keys
 */
class GetFairSigningKeysTest extends WP_UnitTestCase {

	/**
	 * Test should return keys with fair-prefixed fragment IDs.
	 */
	public function test_should_return_fair_prefixed_multikeys() {
		$did_doc = [
			'verificationMethod' => [
				[
					'id'                 => 'did:plc:test#fair-signing',
					'type'               => 'Multikey',
					'publicKeyMultibase' => 'zDnaerDaTF5BXEavCrfRZEk316dpbLsfPDZ3WJ5hRTPFU2169',
				],
				[
					'id'                 => 'did:plc:test#atproto',
					'type'               => 'Multikey',
					'publicKeyMultibase' => 'zQ3shXjHeiBuRCKmM36cuYnm7YEMzhGnCmCyW92sRJ9pribSF',
				],
			],
		];

		$actual = get_fair_signing_keys( $did_doc );

		$this->assertCount( 1, $actual, 'Only fair-prefixed keys should be returned.' );
		$key = reset( $actual );
		$this->assertSame( 'did:plc:test#fair-signing', $key['id'], 'The returned key should have the fair-prefixed ID.' );
	}

	/**
	 * Test should exclude non-Multikey types.
	 */
	public function test_should_exclude_non_multikey_types() {
		$did_doc = [
			'verificationMethod' => [
				[
					'id'                 => 'did:plc:test#fair-signing',
					'type'               => 'Ed25519VerificationKey2020',
					'publicKeyMultibase' => 'zDnaerDaTF5BXEavCrfRZEk316dpbLsfPDZ3WJ5hRTPFU2169',
				],
			],
		];

		$actual = get_fair_signing_keys( $did_doc );

		$this->assertEmpty( $actual, 'Non-Multikey types should be excluded.' );
	}

	/**
	 * Test should return empty when no verificationMethod key exists.
	 */
	public function test_should_return_empty_when_verification_method_missing() {
		$did_doc = [ 'id' => 'did:plc:test' ];

		$actual = get_fair_signing_keys( $did_doc );

		$this->assertEmpty( $actual, 'Expected empty when verificationMethod is missing.' );
	}

	/**
	 * Test should return multiple fair keys.
	 */
	public function test_should_return_multiple_fair_keys() {
		$did_doc = [
			'verificationMethod' => [
				[
					'id'                 => 'did:plc:test#fair-signing',
					'type'               => 'Multikey',
					'publicKeyMultibase' => 'zDnaerDaTF5BXEavCrfRZEk316dpbLsfPDZ3WJ5hRTPFU2169',
				],
				[
					'id'                 => 'did:plc:test#fair-backup',
					'type'               => 'Multikey',
					'publicKeyMultibase' => 'zQ3shXjHeiBuRCKmM36cuYnm7YEMzhGnCmCyW92sRJ9pribSF',
				],
			],
		];

		$actual = get_fair_signing_keys( $did_doc );

		$this->assertCount( 2, $actual, 'Both fair-prefixed keys should be returned.' );
	}
}
