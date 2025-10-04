<?php
/**
 * Tests for functions under FAIR\Packages\Admin\Info namespace.
 *
 * @package FAIR
 */

use function FAIR\Packages\Admin\Info\order_sections_by_predefined_order;

/**
 * Test cases for functions under FAIR\Packages\Admin\Info namespace.
 */
class InfoTest extends WP_UnitTestCase {
	/**
	 * Test that sections are ordered in a predefined order.
	 *
	 * @dataProvider data_plugin_detail_sections
	 *
	 * @param array $sections Sections provided in arbitrary order, as if returned from MetadataDocument.
	 * @param array $ordered_sections The sections in order we expect them to be.
	 */
	public function test_should_return_sections_in_predefined_order( array $sections, array $ordered_sections ) {
		$this->assertSame(
			$ordered_sections,
			order_sections_by_predefined_order( $sections )
		);
	}

	/**
	 * Data provider.
	 */
	public static function data_plugin_detail_sections(): array {
		return [
			'expected sections' => [
				'arbitrary order' => [
					'faq' => '',
					'screenshots' => '',
					'changelog' => '',
					'description' => '',
					'security' => '',
					'reviews' => '',
					'other_notes' => '',
					'installation' => '',
				],
				'expected order' => [
					'description' => '',
					'installation' => '',
					'faq' => '',
					'screenshots' => '',
					'changelog' => '',
					'security' => '',
					'reviews' => '',
					'other_notes' => '',
				],
			],
			'unknown sections' => [
				'arbitrary order' => [
					'foo' => '',
					'bar' => '',
					'baz' => '',
				],
				'expected order' => [
					'foo' => '',
					'bar' => '',
					'baz' => '',
				],
			],
			'expected and unknown sections' => [
				'arbitrary order' => [
					'faq' => '',
					'foo' => '',
					'screenshots' => '',
					'changelog' => '',
					'bar' => '',
					'reviews' => '',
					'installation' => '',
					'security' => '',
				],
				'expected order' => [
					'installation' => '',
					'faq' => '',
					'screenshots' => '',
					'changelog' => '',
					'security' => '',
					'reviews' => '',
					'foo' => '',
					'bar' => '',
				],
			],
			'empty sections' => [
				'arbitrary order' => [],
				'expected order' => [],
			],
		];
	}
}
