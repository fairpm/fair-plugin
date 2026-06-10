<?php
/**
 * Tests for FAIR\Packages\get_language_priority_list().
 *
 * @package FAIR
 */

use function FAIR\Packages\get_language_priority_list;

/**
 * Tests for FAIR\Packages\get_language_priority_list().
 *
 * @covers FAIR\Packages\get_language_priority_list
 */
class GetLanguagePriorityListTest extends WP_UnitTestCase {

	/**
	 * Test should return the locale as the first entry.
	 */
	public function test_should_start_with_full_locale() {
		$actual = get_language_priority_list( 'de-DE' );

		$this->assertSame( 'de-de', $actual[0], 'Full locale should be highest priority.' );
	}

	/**
	 * Test should convert underscores to hyphens.
	 */
	public function test_should_convert_underscores_to_hyphens() {
		$actual = get_language_priority_list( 'de_DE' );

		$this->assertSame( 'de-de', $actual[0], 'Underscore should be converted to hyphen.' );
	}

	/**
	 * Test should lowercase the locale.
	 */
	public function test_should_lowercase_locale() {
		$actual = get_language_priority_list( 'DE-DE' );

		$this->assertSame( 'de-de', $actual[0], 'Locale should be lowercased.' );
	}

	/**
	 * Test should include all prefix decompositions.
	 */
	public function test_should_include_prefixes_in_descending_specificity() {
		$actual = get_language_priority_list( 'zh-Hans-CN' );

		$this->assertSame( 'zh-hans-cn', $actual[0], 'Full locale first.' );
		$this->assertContains( 'zh-hans', $actual, 'Should include zh-hans.' );
		$this->assertContains( 'zh', $actual, 'Should include zh.' );
	}

	/**
	 * Test should skip private-use subtags (x- prefix).
	 *
	 * Per RFC 4647, private-use subtags starting with 'x' should not
	 * generate standalone prefix matches.
	 */
	public function test_should_skip_x_prefix_subtags() {
		$actual = get_language_priority_list( 'en-US-x-private' );

		$this->assertContains( 'en-us', $actual, 'Should include en-us.' );
		$this->assertContains( 'en', $actual, 'Should include en.' );

		// 'en-us-x-private' is the full locale entry (at index 0).
		// 'en-us-x' should NOT appear separately — the function skips it.
		$this->assertNotContains( 'en-us-x', $actual, 'en-us-x should not be a standalone entry.' );
	}

	/**
	 * Test should double the primary language code for country-match fallback.
	 *
	 * e.g. de becomes de-DE.
	 */
	public function test_should_include_doubled_primary_code() {
		$actual = get_language_priority_list( 'de-DE' );

		$this->assertContains( 'de-de', $actual, 'Should include doubled primary code.' );
	}

	/**
	 * Test should include en-us and en as defaults.
	 */
	public function test_should_include_default_locales() {
		$actual = get_language_priority_list( 'fr-FR' );

		$this->assertContains( 'en-us', $actual, 'Should include en-us default.' );
		$this->assertContains( 'en', $actual, 'Should include en default.' );
	}

	/**
	 * Test simple locale (no region) should work.
	 */
	public function test_should_handle_simple_locale_without_region() {
		$actual = get_language_priority_list( 'en' );

		$this->assertSame( 'en', $actual[0], 'Simple locale should be first.' );
	}

	/**
	 * Test should fire the filter hook with correct arguments.
	 */
	public function test_should_fire_filter_hook() {
		$filter_fired = false;

		add_filter(
			'fair.packages.language_priority_list',
			function ( $langs, $locale ) use ( &$filter_fired ) {
				$filter_fired = true;
				$this->assertIsArray( $langs, 'Filter receives array of langs.' );
				$this->assertIsString( $locale, 'Filter receives locale string.' );
				return $langs;
			},
			10,
			2
		);

		get_language_priority_list( 'de-DE' );

		$this->assertTrue( $filter_fired, 'Filter hook should have fired.' );
	}

	/**
	 * Test filter can modify the priority list.
	 */
	public function test_filter_can_modify_priority_list() {
		add_filter( 'fair.packages.language_priority_list', fn() => [ 'custom-only' ], 999 );

		$actual = get_language_priority_list( 'de-DE' );

		$this->assertSame( [ 'custom-only' ], $actual, 'Filter should be able to override the list.' );
	}

	/**
	 * Test defaults to current locale when no locale is passed.
	 */
	public function test_should_default_to_current_locale_when_null() {
		// Switch to a known locale.
		switch_to_locale( 'es_ES' );

		$actual = get_language_priority_list( null );

		$this->assertSame( 'es-es', $actual[0], 'Should use current WP locale when null passed.' );

		switch_to_locale( 'en_US' );
	}

	/**
	 * Test the zh-Hans-CN three-component locale decomposition order.
	 */
	public function test_should_decompose_in_correct_order_for_three_component_locale() {
		$actual = get_language_priority_list( 'zh-Hans-CN' );

		// Order should be: full → zh-Hans → zh → zh-zh → en-us → en
		$this->assertSame( 'zh-hans-cn', $actual[0], 'Full locale first.' );

		// Find the positions.
		$pos_zh_hans = array_search( 'zh-hans', $actual, true );
		$pos_zh      = array_search( 'zh', $actual, true );
		$pos_zh_zh   = array_search( 'zh-zh', $actual, true );

		$this->assertNotFalse( $pos_zh_hans, 'zh-hans should be present.' );
		$this->assertNotFalse( $pos_zh, 'zh should be present.' );
		$this->assertNotFalse( $pos_zh_zh, 'zh-zh should be present.' );

		// More specific should come before less specific.
		$this->assertLessThan( $pos_zh, $pos_zh_hans, 'zh-Hans should come before zh.' );
	}
}
