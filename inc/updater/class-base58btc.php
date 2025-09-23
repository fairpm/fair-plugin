<?php
/**
 * A port of yocLIB Multibase Base58 encoding/decoding by Yocto.
 *
 * Original source: https://github.com/yocto/yoclib-multibase-php
 * License: GPL-3.0
 */

namespace FAIR\Updater;

/**
 * Base58BTC encoding and decoding.
 */
class Base58BTC {
	const PREFIX = 'z';
	const ALPHABET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

	/**
	 * @param array $source
	 * @param int $sourceBase
	 * @param int $targetBase
	 * @return array
	 */
	private static function convertBase(array $source, int $sourceBase, int $targetBase): array{
		$result = [];
		while ($count = count($source)) {
			$quotient = [];
			$remainder = 0;
			for ($i = 0; $i !== $count; $i++) {
				$accumulator = $source[$i] + $remainder * $sourceBase;
				/* Same as PHP 7 intdiv($accumulator, $targetBase) */
				$digit = ($accumulator - ($accumulator % $targetBase)) / $targetBase;
				$remainder = $accumulator % $targetBase;
				if (count($quotient) || $digit) {
					$quotient[] = $digit;
				}
			}
			array_unshift($result, $remainder);
			$source = $quotient;
		}

		return $result;
	}

	/**
	 * @param string $data
	 * @param string $alphabet
	 * @return string
	 */
	public static function decode(string $data): string{
		$data = str_split($data);

		if ($data[0] === self::PREFIX) {
			array_shift($data);
		}

		$data = array_map(static function($character) {
			return strpos(self::ALPHABET,$character);
		}, $data);

		$leadingZeroes = 0;
		while (!empty($data) && 0 === $data[0]) {
			$leadingZeroes++;
			array_shift($data);
		}

		$converted = self::convertBase($data, 58, 256);

		if (0 < $leadingZeroes) {
			$converted = array_merge(
				array_fill(0, $leadingZeroes, 0),
				$converted
			);
		}

		return implode("", array_map("chr", $converted));
	}

	public static function encode(string $data,string $alphabet): string{
		$data = str_split($data);
		$data = array_map("ord", $data);

		$leadingZeroes = 0;
		while (!empty($data) && 0 === $data[0]) {
			$leadingZeroes++;
			array_shift($data);
		}

		$converted = self::convertBase($data, 256, 58);

		if (0 < $leadingZeroes) {
			$converted = array_merge(
				array_fill(0, $leadingZeroes, 0),
				$converted
			);
		}

		return implode('',array_map(static function($index) use($alphabet){
			return $alphabet[$index];
		},$converted));
	}

}
