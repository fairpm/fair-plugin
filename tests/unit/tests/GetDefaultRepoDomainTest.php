<?php
/**
 * Tests for FAIR\Default_Repo\get_default_repo_domain().
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

