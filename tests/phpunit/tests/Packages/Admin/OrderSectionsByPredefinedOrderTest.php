<?php
/**
 * Tests for FAIR\Packages\Admin\order_sections_by_predefined_order().
 *
 * @package FAIR
 */

use function FAIR\Packages\Admin\order_sections_by_predefined_order;

/**
 * Tests for FAIR\Packages\Admin\order_sections_by_predefined_order().
 *
 * @covers FAIR\Packages\Admin\order_sections_by_predefined_order
 */
class OrderSectionsByPredefinedOrderTest extends WP_UnitTestCase {

	/**
	 * Test that sections are ordered in a predefined order.
	 *
	 * @dataProvider data_plugin_detail_sections
	 *
	 * @param array $sections Sections provided in arbitrary order, as if returned from MetadataDocument.
	 * @param array $expected_order The sections in order we expect them to be.
	 */
	public function test_should_return_sections_in_predefined_order( array $sections, array $expected_order ) {
		$this->assertSame(
			$expected_order,
			order_sections_by_predefined_order( $sections )
		);
	}

	/**
	 * Data provider.
	 */
	public static function data_plugin_detail_sections(): array {
		return [
			'expected sections' => [
				'sections' => [
					'faq' => '',
					'screenshots' => '',
					'changelog' => '',
					'description' => '',
					'security' => '',
					'reviews' => '',
					'other_notes' => '',
					'installation' => '',
				],
				'expected_order' => [
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
				'sections' => [
					'foo' => '',
					'bar' => '',
					'baz' => '',
				],
				'expected_order' => [
					'foo' => '',
					'bar' => '',
					'baz' => '',
				],
			],
			'expected and unknown sections' => [
				'sections' => [
					'faq' => '',
					'foo' => '',
					'screenshots' => '',
					'changelog' => '',
					'bar' => '',
					'reviews' => '',
					'installation' => '',
					'security' => '',
				],
				'expected_order' => [
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
				'sections' => [],
				'expected_order' => [],
			],
		];
	}
}
