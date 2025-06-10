<?php
/**
 * File structure
 * --------------
 * - Each <Module> should have its own directory.
 * - Each <FunctionUnderTest> should have its own file.
 *
 * Formats
 * -------
 * - File name: tests/Module/FunctionNameTest.php
 * - Class name: FunctionNameTest
 * - Method name: test_should(_not)_do_<something>[_when_<condition>]()
 *
 * Coverage
 * --------
 * - @covers tags should be placed at the class level and target the FunctionUnderTest.
 * - Since each <FunctionUnderTest> has its own test class, @coversDefaultClass should not be used.
 *
 * Using Assertions
 * ----------------
 * See https://docs.phpunit.de/en/10.5/assertions.html for available assertions.
 *
 * The strictest possible assertion should always be used.
 *
 * If multiple assertions are used in the same test method, a final '$message' argument should
 * be passed to each assertion to provide context should the assertion fail. This can help
 * to identify exactly when and how something isn't working as expected.
 *
 * Testing multiple values
 * -----------------------
 * When testing multiple values, a data provider method should be used.
 *
 * - Data provider methods should be prefixed with "data_".
 * - Datasets and their properties should be named.
 */

/**
 * Tests for FAIR\Namespace\FunctionUnderTest().
 *
 * @covers FAIR\Namespace\FunctionUnderTest
 */
class SampleTest extends WP_UnitTestCase {
	/**
	 * Tests that true is true.
	 */
	public function test_should_return_true() {
		$actual = true;

		$this->assertTrue( $actual );
	}

	/**
	 * Tests that false is not true.
	 */
	public function test_should_not_think_false_is_true() {
		$actual = false;

		$this->assertNotTrue( $actual );
	}

	/**
	 * Tests that multiple values are not true.
	 *
	 * @dataProvider data_not_true_values
	 *
	 * @param mixed $value The value to test.
	 */
	public function test_should_not_be_true( $value ) {
		$this->assertNotTrue( $value );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_not_true_values() {
		return [
			// Think: "Running test with <dataset name>".
			'(int) 1' => [
				'value' => 1,
			],
			'an empty array' => [
				'value' => [],
			],
			'a populated array' => [
				'value' => [ 'populated' ],
			],
			// And so on.
		];
	}
}
