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

	// Edge cases intentionally not pinned down here: tie-breaking across multiple
	// matching lang values, artifacts without lang, and fallback precedence across
	// partial locale matches.

	/**
	 * Test should prefer an exact locale match over artifacts without lang.
	 */
	public function test_should_prefer_exact_locale_match_over_artifact_without_lang() {
		$fallback_artifact = (object) [
			'url' => 'https://example.com/no-lang.zip',
		];
		$matching_artifact = (object) [
			'url' => 'https://example.com/de-de.zip',
			'lang' => 'de-DE',
		];

		$selected = pick_artifact_by_lang( [ $fallback_artifact, $matching_artifact ], 'de-DE' );

		$this->assertSame( $matching_artifact, $selected, 'The exact locale match should be selected.' );
	}

	/**
	 * Test should not fail when artifacts do not specify lang.
	 */
	public function test_should_return_an_artifact_when_lang_is_missing() {
		$first_artifact = (object) [
			'url' => 'https://example.com/first.zip',
		];
		$second_artifact = (object) [
			'url' => 'https://example.com/second.zip',
		];

		$selected = pick_artifact_by_lang( [ $first_artifact, $second_artifact ], 'de-DE' );

		$this->assertContains( $selected, [ $first_artifact, $second_artifact ], 'Artifacts without lang should still return a valid artifact.' );
	}
}
