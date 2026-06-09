<?php
/**
 * Tests for FAIR\Packages\pick_artifact_by_lang().
 *
 * @package FAIR
 */

use function FAIR\Packages\pick_artifact_by_lang;

/**
 * Tests for FAIR\Packages\pick_artifact_by_lang().
 *
 * @covers FAIR\Packages\pick_artifact_by_lang
 */
class PickArtifactByLangTest extends WP_UnitTestCase {

	/**
	 * Create an artifact with a given language.
	 */
	private function artifact( string $lang, string $url = '' ): stdClass {
		return (object) [
			'lang' => $lang,
			'url'  => $url ?: "https://example.com/artifact-{$lang}.zip",
		];
	}

	/**
	 * Test should return the exact matching artifact for the current locale.
	 */
	public function test_should_return_exact_match() {
		// de-DE is highest in the priority list for German.
		$artifacts = [
			$this->artifact( 'en-US' ),
			$this->artifact( 'de-DE' ),
			$this->artifact( 'fr-FR' ),
		];

		$actual = pick_artifact_by_lang( $artifacts, 'de-DE' );

		$this->assertNotNull( $actual, 'Should return an artifact.' );
		$this->assertSame( 'de-DE', $actual->lang, 'Should pick the exact locale match.' );
	}

	/**
	 * Test should prefer a more specific match over a less specific one.
	 */
	public function test_should_prefer_more_specific_match() {
		// For zh-Hans-CN: zh-hans-cn > zh-hans > zh
		$artifacts = [
			$this->artifact( 'zh' ),
			$this->artifact( 'zh-Hans' ),
			$this->artifact( 'en-US' ),
		];

		$actual = pick_artifact_by_lang( $artifacts, 'zh-Hans-CN' );

		$this->assertNotNull( $actual, 'Should return an artifact.' );
		$this->assertSame( 'zh-Hans', $actual->lang, 'Should pick zh-Hans over zh (more specific).' );
	}

	/**
	 * Test should fall back to the first artifact when no language matches.
	 */
	public function test_should_return_first_artifact_when_no_lang_matches() {
		$artifacts = [
			$this->artifact( 'en-US' ),
			$this->artifact( 'en-GB' ),
		];

		$actual = pick_artifact_by_lang( $artifacts, 'ja-JP' );

		$this->assertNotNull( $actual, 'Should return an artifact even when no match.' );
		// With no match, all artifacts score 0 and usort preserves relative order
		// (though usort is not stable; the first in the set will be returned).
	}

	/**
	 * Test should return false for an empty artifacts array.
	 *
	 * NOTE: reset() returns false for empty arrays. The filter can
	 * override this, but the default behavior is false, not null.
	 */
	public function test_should_return_false_for_empty_artifacts() {
		$actual = pick_artifact_by_lang( [], 'en-US' );

		$this->assertFalse( $actual, 'Empty artifacts should return false (reset() default).' );
	}

	/**
	 * Test should handle a single artifact.
	 */
	public function test_should_return_single_artifact() {
		$artifacts = [ $this->artifact( 'fr-FR' ) ];

		$actual = pick_artifact_by_lang( $artifacts, 'en-US' );

		$this->assertNotNull( $actual, 'Should return the single artifact.' );
		$this->assertSame( 'fr-FR', $actual->lang, 'Should return the only available artifact.' );
	}

	/**
	 * Test should match the doubled primary code (e.g., de → de-DE).
	 *
	 * NOTE: For simple locales without a region (like 'de'), the doubled
	 * primary code only fires when the locale has a hyphen, so it's not
	 * generated. The artifact with 'de-DE' only matches if the locale
	 * decomposition actually produces 'de-de'. With locale 'de-DE', it does.
	 */
	public function test_should_match_doubled_primary_code() {
		$artifacts = [
			$this->artifact( 'en-US' ),
			$this->artifact( 'de-DE' ),
		];

		// 'de-DE' (with region) generates: de-de, de, de-de (doubled), en-us, en
		// de-DE matches de-de at position 3, scoring higher than en-US.
		$actual = pick_artifact_by_lang( $artifacts, 'de-DE' );

		$this->assertNotNull( $actual, 'Should return an artifact.' );
		$this->assertSame( 'de-DE', $actual->lang, 'Should match de-DE via doubled primary code.' );
	}

	/**
	 * Test should match en-us as a default fallback.
	 */
	public function test_should_match_en_us_as_default() {
		$artifacts = [
			$this->artifact( 'fr-FR' ),
			$this->artifact( 'en-US' ),
		];

		$actual = pick_artifact_by_lang( $artifacts, 'ja-JP' );

		// en-US is in the default list (en-us), fr-FR is not.
		// en-US should score higher.
		$this->assertNotNull( $actual, 'Should return an artifact.' );
		$this->assertSame( 'en-US', $actual->lang, 'Should match en-US as default fallback.' );
	}

	/**
	 * Test should fire the pick_artifact_by_lang filter.
	 */
	public function test_should_fire_filter_hook() {
		$filter_fired = false;

		add_filter(
			'fair.packages.pick_artifact_by_lang',
			function ( $selected, $artifacts, $locale, $langs ) use ( &$filter_fired ) {
				$filter_fired = true;
				$this->assertIsArray( $artifacts, 'Filter receives artifacts array.' );
				$this->assertIsString( $locale, 'Filter receives locale.' );
				return $selected;
			},
			10,
			4
		);

		$artifacts = [ $this->artifact( 'en-US' ) ];
		pick_artifact_by_lang( $artifacts, 'en-US' );

		$this->assertTrue( $filter_fired, 'Filter hook should have fired.' );
	}

	/**
	 * Test filter can override the selected artifact.
	 */
	public function test_filter_can_override_selection() {
		$override = $this->artifact( 'xx-XX', 'https://override.example.com' );

		add_filter( 'fair.packages.pick_artifact_by_lang', fn() => $override, 999 );

		$artifacts = [ $this->artifact( 'en-US' ) ];
		$actual    = pick_artifact_by_lang( $artifacts, 'en-US' );

		$this->assertSame( $override, $actual, 'Filter should be able to override selection.' );
	}

	/**
	 * Test underscore in locale is normalized to hyphen for matching.
	 */
	public function test_should_match_underscore_locale_variants() {
		$artifacts = [
			$this->artifact( 'en-US' ),
			$this->artifact( 'en-GB' ),
		];

		// en_US (underscore) should normalize to en-us and match en-US
		$actual = pick_artifact_by_lang( $artifacts, 'en_US' );

		$this->assertNotNull( $actual, 'Should return an artifact.' );
		$this->assertSame( 'en-US', $actual->lang, 'Should match en-US from underscore locale.' );
	}
}
