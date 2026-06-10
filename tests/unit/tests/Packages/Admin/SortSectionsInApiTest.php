<?php
/**
 * Tests for FAIR\Packages\Admin\sort_sections_in_api().
 *
 * @package FAIR
 */

use function FAIR\Packages\Admin\sort_sections_in_api;

/**
 * Tests for FAIR\Packages\Admin\sort_sections_in_api().
 *
 * @covers FAIR\Packages\Admin\sort_sections_in_api
 */
class SortSectionsInApi extends WP_UnitTestCase {

	/**
	 * Test that sections are ordered in a predefined order.
	 *
	 * @dataProvider data_plugin_detail_sections
	 *
	 * @param array $sections Sections provided in arbitrary order, as if returned from MetadataDocument.
	 * @param array $expected_order The sections in order we expect them to be.
	 */
	public function test_should_return_sections_in_predefined_order( array $sections, array $expected_order ) {
		$res = new stdClass();
		$res->sections = $sections;
		$actual = sort_sections_in_api( $res );
		$this->assertIsObject( $actual, 'The response is not an object.' );
		// $this->assertObjectHasProperty( 'sections', $actual, 'The response object has no sections.' );
		$this->assertSame(
			$expected_order,
			$actual->sections,
			'The sections were not in the expected order.'
		);
	}

	/**
	 * Data provider.
	 */
	public static function data_plugin_detail_sections(): array {
		return [
			'expected sections' => [
				'sections' => [
					'faq' => 'faq',
					'screenshots' => 'screenshots',
					'changelog' => 'changelog',
					'description' => 'description',
					'security' => 'security',
					'reviews' => 'reviews',
					'other_notes' => 'other_notes',
					'installation' => 'installation',
					'upgrade_notice' => 'upgrade_notice',
				],
				'expected_order' => [
					'description' => 'description',
					'installation' => 'installation',
					'faq' => 'faq',
					'screenshots' => 'screenshots',
					'changelog' => 'changelog',
					'upgrade_notice' => 'upgrade_notice',
					'security' => 'security',
					'other_notes' => 'other_notes',
					'reviews' => 'reviews',
				],
			],
			'unknown sections' => [
				'sections' => [
					'foo' => 'foo',
					'bar' => 'bar',
					'baz' => 'baz',
				],
				'expected_order' => [
					'foo' => 'foo',
					'bar' => 'bar',
					'baz' => 'baz',
				],
			],
			'expected and unknown sections' => [
				'sections' => [
					'faq' => 'faq',
					'foo' => 'foo',
					'screenshots' => 'screenshots',
					'changelog' => 'changelog',
					'bar' => 'bar',
					'reviews' => 'reviews',
					'installation' => 'installation',
					'security' => 'security',
				],
				'expected_order' => [
					'installation' => 'installation',
					'faq' => 'faq',
					'screenshots' => 'screenshots',
					'changelog' => 'changelog',
					'security' => 'security',
					'reviews' => 'reviews',
					'foo' => 'foo',
					'bar' => 'bar',
				],
			],
			'some empty sections' => [
				'sections' => [
					'faq' => '',
					'foo' => '',
					'screenshots' => 'screenshots',
					'changelog' => '',
					'bar' => '',
					'reviews' => 'reviews',
					'installation' => '',
					'security' => '',
				],
				'expected_order' => [
					'screenshots' => 'screenshots',
					'reviews' => 'reviews',
				],
			],
			'no sections' => [
				'sections' => [],
				'expected_order' => [],
			],
		];
	}
}
