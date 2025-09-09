<?php
namespace YOCLIB\Multiformats\Multibase;

class Base36{

	/**
	 * @param string $base36
	 * @return string
	 */
	public static function decode(string $base36): string{
		$hex = BaseUtil::str_baseconvert(strtolower($base36),36,16);
		foreach(str_split($base36) AS $chunk){
			if($chunk!=="0"){
				break;
			}
			$hex = '00'.$hex;
		}
		return hex2bin($hex);
	}

	/**
	 * @param string $data
	 * @return string
	 */
	public static function encode(string $data): string{
		$hex = bin2hex($data);
		$base36 = BaseUtil::str_baseconvert($hex,16,36);
		foreach(str_split($data) AS $chunk){
			if($chunk!=="\0"){
				break;
			}
			$base36 = '0'.$base36;
		}

		return $base36;
	}

}