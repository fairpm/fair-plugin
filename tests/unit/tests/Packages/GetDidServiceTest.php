<?php
/**
 * Tests for FAIR\Packages\get_did_service().
 *
 * @package FAIR
 */

use function FAIR\Packages\get_did_service;

/**
 * Tests for FAIR\Packages\get_did_service().
 *
 * @covers FAIR\Packages\get_did_service
 */
class GetDidServiceTest extends WP_UnitTestCase {

	/**
	 * Test should return matching service.
	 */
	public function test_should_return_matching_service() {
		$did_doc = [
			'service' => [
				[
					'id'              => '#fair-repo',
					'type'            => 'FairPackageManagementRepo',
					'serviceEndpoint' => 'https://example.com/fair',
				],
				[
					'id'              => '#atproto-pds',
					'type'            => 'AtprotoPersonalDataServer',
					'serviceEndpoint' => 'https://pds.example.com',
				],
			],
		];

		$actual = get_did_service( $did_doc, 'FairPackageManagementRepo' );

		$this->assertIsArray( $actual, 'Expected an array for a matching service.' );
		$this->assertSame( 'https://example.com/fair', $actual['serviceEndpoint'], 'Service endpoint should match.' );
	}

	/**
	 * Test should return null when no service matches.
	 */
	public function test_should_return_null_when_no_match() {
		$did_doc = [
			'service' => [
				[
					'id'              => '#atproto-pds',
					'type'            => 'AtprotoPersonalDataServer',
					'serviceEndpoint' => 'https://pds.example.com',
				],
			],
		];

		$actual = get_did_service( $did_doc, 'FairPackageManagementRepo' );

		$this->assertNull( $actual, 'Expected null when no service matches.' );
	}

	/**
	 * Test should return null when service array is empty.
	 */
	public function test_should_return_null_for_empty_services() {
		$did_doc = [ 'service' => [] ];

		$actual = get_did_service( $did_doc, 'FairPackageManagementRepo' );

		$this->assertNull( $actual, 'Expected null for empty services.' );
	}

	/**
	 * Test should return null when service key is missing.
	 */
	public function test_should_return_null_when_service_key_missing() {
		$did_doc = [ 'id' => 'did:plc:test' ];

		$actual = get_did_service( $did_doc, 'FairPackageManagementRepo' );

		$this->assertNull( $actual, 'Expected null when service key is missing.' );
	}
}
