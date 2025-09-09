<?php
namespace YOCLIB\Multiformats\Multibase;

class BaseUtil{

    /**
     * @param $number
     * @param $fromBase
     * @param $toBase
     * @return string
     */
	public static function str_baseconvert($number, $fromBase, $toBase): string{
		$digits = '0123456789abcdefghijklmnopqrstuvwxyz';
		$length = strlen($number);
		$result = '';

		$nibbles = array();
		for ($i = 0; $i < $length; ++$i) {
			$nibbles[$i] = strpos($digits, $number[$i]);
		}

		do {
			$value = 0;
			$newlen = 0;
			for ($i = 0; $i < $length; ++$i) {
				$value = $value * $fromBase + $nibbles[$i];
				if ($value >= $toBase) {
					$nibbles[$newlen++] = (int)($value / $toBase);
					$value %= $toBase;
				}
				else if ($newlen > 0) {
					$nibbles[$newlen++] = 0;
				}
			}
			$length = $newlen;
			$result = $digits[$value].$result;
		}
		while ($newlen != 0);
		return $result;
	}

}