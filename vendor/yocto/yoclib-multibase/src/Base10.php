<?php
namespace YOCLIB\Multiformats\Multibase;

class Base10{

	/**
	 * @param string $decimal
	 * @return string
	 */
	public static function decode(string $decimal): string{
		$hex = BaseUtil::str_baseconvert($decimal,10,16);
		while(strlen($hex)%2!==0){
			$hex = '0'.$hex;
		}
		foreach(str_split($decimal) AS $chunk){
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
		$decimal = BaseUtil::str_baseconvert($hex,16,10);
		foreach(str_split($data) AS $chunk){
			if($chunk!=="\0"){
				break;
			}
			$decimal = '0'.$decimal;
		}
		return $decimal;
	}

}