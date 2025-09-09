<?php
namespace YOCLIB\Multiformats\Multibase;

class Base64{

	/**
	 * @param string $base64
	 * @return string
	 */
	public static function decode(string $base64): string{
		return base64_decode($base64);
	}

	/**
	 * @param string $data
	 * @return string
	 */
	public static function encode(string $data): string{
		return base64_encode($data);
	}

}