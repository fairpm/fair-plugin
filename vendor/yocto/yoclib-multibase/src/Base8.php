<?php
namespace YOCLIB\Multiformats\Multibase;

class Base8{

	/**
	 * @param string $octal
	 * @return string
	 */
	public static function decode(string $octal): string{
		$binary = '';
		for($i=0;$i<strlen($octal);$i++){
			$binaryChunk = decbin(octdec($octal[$i]));
			while(strlen($binaryChunk)%3!==0){
				$binaryChunk = '0'.$binaryChunk;
			}
			$binary .= $binaryChunk;
		}
		$padding = strlen($binary)%8;
		if($padding!==0){
			$binary = substr($binary,0,-$padding);
		}
		return Base2::decode($binary);
	}

	/**
	 * @param string $data
	 * @return string
	 */
	public static function encode(string $data): string{
		$binary = Base2::encode($data);
		$octal = '';
		foreach(str_split($binary,3) AS $chunk){
			while(strlen($chunk)%3!==0){
				$chunk .= '0';
			}
			$octalChunk = decoct(bindec($chunk));
			$octal .= $octalChunk;
		}
		return $octal;
	}

}