<?php
/**
 * Polyfill for PHP functions not included in minimum supported version.
 * Requires PHP 7.4
 */

if ( ! function_exists( 'str_contains' ) ) {
	/**
	 * Polyfill for `str_contains()` function added in PHP 8.0.
	 *
	 * Performs a case-sensitive check indicating if needle is
	 * contained in haystack.
	 *
	 * @since WordPress 5.9.0
	 *
	 * @param string $haystack The string to search in.
	 * @param string $needle   The substring to search for in the `$haystack`.
	 * @return bool True if `$needle` is in `$haystack`, otherwise false.
	 */
	function str_contains( $haystack, $needle ) {
		if ( '' === $needle ) {
			return true;
		}

		return false !== strpos( $haystack, $needle );
	}
}

if ( ! function_exists( 'str_starts_with' ) ) {
	/**
	 * Polyfill for `str_starts_with()` function added in PHP 8.0.
	 *
	 * Performs a case-sensitive check indicating if
	 * the haystack begins with needle.
	 *
	 * @since WordPress 5.9.0
	 *
	 * @param string $haystack The string to search in.
	 * @param string $needle   The substring to search for in the `$haystack`.
	 * @return bool True if `$haystack` starts with `$needle`, otherwise false.
	 */
	function str_starts_with( $haystack, $needle ) {
		if ( '' === $needle ) {
			return true;
		}

		return 0 === strpos( $haystack, $needle );
	}
}

if ( ! function_exists( 'str_ends_with' ) ) {
	/**
	 * Polyfill for `str_ends_with()` function added in PHP 8.0.
	 *
	 * Performs a case-sensitive check indicating if
	 * the haystack ends with needle.
	 *
	 * @since WordPress 5.9.0
	 *
	 * @param string $haystack The string to search in.
	 * @param string $needle   The substring to search for in the `$haystack`.
	 * @return bool True if `$haystack` ends with `$needle`, otherwise false.
	 */
	function str_ends_with( $haystack, $needle ) {
		if ( '' === $haystack ) {
			return '' === $needle;
		}

		$len = strlen( $needle );

		return substr( $haystack, -$len, $len ) === $needle;
	}
}

if ( ! function_exists( 'array_is_list' ) ) {
	/**
	 * Polyfill for `array_is_list()` function added in PHP 8.1.
	 *
	 * Determines if the given array is a list.
	 *
	 * An array is considered a list if its keys consist of consecutive numbers from 0 to count($array)-1.
	 *
	 * @see https://github.com/symfony/polyfill-php81/tree/main
	 *
	 * @since WordPress 6.5.0
	 *
	 * @param array<mixed> $arr The array being evaluated.
	 * @return bool True if array is a list, false otherwise.
	 */
	function array_is_list( $arr ) {
		if ( ( [] === $arr ) || ( array_values( $arr ) === $arr ) ) {
			return true;
		}

		$next_key = -1;

		foreach ( $arr as $k => $v ) {
			if ( ++$next_key !== $k ) {
				return false;
			}
		}

		return true;
	}
}

if ( ! function_exists( 'array_find' ) ) {
	/**
	 * Polyfill for `array_find()` function added in PHP 8.4.
	 *
	 * Searches an array for the first element that passes a given callback.
	 *
	 * @since WordPress 6.8.0
	 *
	 * @param array    $array    The array to search.
	 * @param callable $callback The callback to run for each element.
	 * @return mixed|null The first element in the array that passes the `$callback`, otherwise null.
	 */
	function array_find( array $array, callable $callback ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.arrayFound
		foreach ( $array as $key => $value ) {
			if ( $callback( $value, $key ) ) {
				return $value;
			}
		}

		return null;
	}
}

if ( ! function_exists( 'array_find_key' ) ) {
	/**
	 * Polyfill for `array_find_key()` function added in PHP 8.4.
	 *
	 * Searches an array for the first key that passes a given callback.
	 *
	 * @since WordPress 6.8.0
	 *
	 * @param array    $array    The array to search.
	 * @param callable $callback The callback to run for each element.
	 * @return int|string|null The first key in the array that passes the `$callback`, otherwise null.
	 */
	function array_find_key( array $array, callable $callback ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.arrayFound
		foreach ( $array as $key => $value ) {
			if ( $callback( $value, $key ) ) {
				return $key;
			}
		}

		return null;
	}
}

if ( ! function_exists( 'array_any' ) ) {
	/**
	 * Polyfill for `array_any()` function added in PHP 8.4.
	 *
	 * Checks if any element of an array passes a given callback.
	 *
	 * @since WordPress 6.8.0
	 *
	 * @param array    $array    The array to check.
	 * @param callable $callback The callback to run for each element.
	 * @return bool True if any element in the array passes the `$callback`, otherwise false.
	 */
	function array_any( array $array, callable $callback ): bool { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.arrayFound
		foreach ( $array as $key => $value ) {
			if ( $callback( $value, $key ) ) {
				return true;
			}
		}

		return false;
	}
}

if ( ! function_exists( 'array_all' ) ) {
	/**
	 * Polyfill for `array_all()` function added in PHP 8.4.
	 *
	 * Checks if all elements of an array pass a given callback.
	 *
	 * @since WordPress 6.8.0
	 *
	 * @param array    $array    The array to check.
	 * @param callable $callback The callback to run for each element.
	 * @return bool True if all elements in the array pass the `$callback`, otherwise false.
	 */
	function array_all( array $array, callable $callback ): bool { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.arrayFound
		foreach ( $array as $key => $value ) {
			if ( ! $callback( $value, $key ) ) {
				return false;
			}
		}

		return true;
	}
}

if ( ! function_exists( 'array_first' ) ) {
	/**
	 * Polyfill for `array_first()` function added in PHP 8.5.
	 *
	 * Returns the first element of an array.
	 *
	 * @since WordPress 6.9.0
	 *
	 * @param array $array The array to get the first element from.
	 * @return mixed|null The first element of the array, or null if the array is empty.
	 */
	function array_first( array $array ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.arrayFound
		if ( empty( $array ) ) {
			return null;
		}

		foreach ( $array as $value ) {
			return $value;
		}
	}
}

if ( ! function_exists( 'array_last' ) ) {
	/**
	 * Polyfill for `array_last()` function added in PHP 8.5.
	 *
	 * Returns the last element of an array.
	 *
	 * @since WordPress 6.9.0
	 *
	 * @param array $array The array to get the last element from.
	 * @return mixed|null The last element of the array, or null if the array is empty.
	 */
	function array_last( array $array ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.arrayFound
		if ( empty( $array ) ) {
			return null;
		}

		return $array[ array_key_last( $array ) ];
	}
}

// IMAGETYPE_AVIF constant is only defined in PHP 8.x or later.
if ( ! defined( 'IMAGETYPE_AVIF' ) ) {
	define( 'IMAGETYPE_AVIF', 19 );
}

// IMG_AVIF constant is only defined in PHP 8.x or later.
if ( ! defined( 'IMG_AVIF' ) ) {
	define( 'IMG_AVIF', IMAGETYPE_AVIF );
}

// IMAGETYPE_HEIC constant is not yet defined in PHP as of PHP 8.3.
if ( ! defined( 'IMAGETYPE_HEIC' ) ) {
	define( 'IMAGETYPE_HEIC', 99 );
}
