<?php
namespace YOCLIB\Multiformats\Multibase;

class Base32{

	/**
	 * @param string $data
	 * @param string $alphabet
	 * @return string
	 */
	public static function decode(string $data,string $alphabet): string{
		$flippedAlphabet = array_flip(str_split($alphabet));
		$binary = '';
		for($i=0;$i<strlen($data);$i++){
			$char = strtolower($data[$i]);
			if($char==='='){
				continue;
			}
			$binaryChunk = decbin($flippedAlphabet[$char]);
			while(strlen($binaryChunk)%5!==0){
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
	 * TODO Make clean
	 * @param string $data
	 * @param string $alphabet
	 * @return string
	 */
	public static function encode(string $data,string $alphabet): string{
		// Empty string results in empty string
		if ('' === $data) {
			return '';
		}

		$encoded = '';

		//Set the initial values
		$n = $bitLen = $val = 0;
		$len = strlen($data);

		//Pad the end of the string - this ensures that there are enough zeros
		$data .= str_repeat(chr(0), 4);

		//Explode string into integers
		$chars = (array) unpack('C*', $data);

		while ($n < $len || 0 !== $bitLen) {
			//If the bit length has fallen below 5, shift left 8 and add the next character.
			if ($bitLen < 5) {
				$val = $val << 8;
				$bitLen += 8;
				$n++;
				$val += $chars[$n];
			}
			$shift = $bitLen - 5;
			$encoded .= ($n - (int)($bitLen > 8) > $len && 0 == $val) ? '=' : $alphabet[$val >> $shift];
			$val = $val & ((1 << $shift) - 1);
			$bitLen -= 5;
		}

		return strtolower($encoded);
	}

}