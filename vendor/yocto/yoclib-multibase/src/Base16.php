<?php
namespace YOCLIB\Multiformats\Multibase;

class Base16{

	/**
	 * @param string $hex
	 * @return string
	 */
	public static function decode(string $hex): string{
		return hex2bin($hex);
	}

	/**
	 * @param string $data
	 * @return string
	 */
	public static function encode(string $data): string{
		return bin2hex($data);
	}

}