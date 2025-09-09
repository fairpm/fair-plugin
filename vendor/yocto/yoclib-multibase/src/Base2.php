<?php
namespace YOCLIB\Multiformats\Multibase;

class Base2{

	/**
	 * @param string $binary
	 * @return string
	 */
	public static function decode(string $binary): string{
		$data = '';
		foreach(str_split($binary,8) AS $chunk){
			$dataChunk = chr(bindec($chunk));
			$data .= $dataChunk;
		}
		return $data;
	}

	/**
	 * @param string $data
	 * @return string
	 */
	public static function encode(string $data): string{
		$binary = '';
		foreach(str_split($data) AS $chunk){
			$binaryChunk = decbin(ord($chunk));
			while(strlen($binaryChunk)%8!==0){
				$binaryChunk = '0'.$binaryChunk;
			}
			$binary .= $binaryChunk;
		}
		return $binary;
	}

}