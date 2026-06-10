<?php
/**
 * Tests for FAIR\Default_Repo and FAIR\\Version_Check functions.
 *
 * @package FAIR
 */

use function FAIR\Default_Repo\get_default_repo_domain;

/**
 * Tests for FAIR\Default_Repo\get_default_repo_domain().
 *
 * @covers FAIR\Default_Repo\get_default_repo_domain
 */
class GetDefaultRepoDomainTest extends WP_UnitTestCase {

	/**
	 * Test should return default domain when constant not set.
	 */
	public function test_should_return_default_domain() {
		$actual = get_default_repo_domain();

		$this->assertSame( 'api.aspirecloud.net', $actual, 'Default domain should be aspirecloud.net.' );
	}

	/**
	 * Test should return non-empty string.
	 */
	public function test_should_return_non_empty_string() {
		$this->assertNotEmpty( get_default_repo_domain(), 'Domain should never be empty.' );
	}
}

/**
 * Tests for FAIR\Version_Check constants.
 *
 * Merged from VersionCheckConstantsTest; only the minimum<recommended
 * assertion has behavioral value — regex/non-empty checks on hardcoded
 * constants are tautological.
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
