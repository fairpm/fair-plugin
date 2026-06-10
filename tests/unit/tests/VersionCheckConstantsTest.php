<?php
/**
 * Tests for FAIR\Version_Check constants.
 *
 * @package FAIR
 */

/**
 * Tests for FAIR\Version_Check constants.
 *
 * @coversNothing — testing constant guardrails.
 */
class VersionCheckConstantsTest extends WP_UnitTestCase {

	/**
	 * Test minimum < recommended (no regression).
	 */
	public function test_minimum_is_less_than_recommended(): void {
		$this->assertTrue(
			version_compare( \FAIR\Version_Check\MINIMUM_PHP, \FAIR\Version_Check\RECOMMENDED_PHP, '<' ),
			'Minimum PHP should be less than recommended.'
		);
	}
}
