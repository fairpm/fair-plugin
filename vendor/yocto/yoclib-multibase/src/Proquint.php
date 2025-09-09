<?php
namespace YOCLIB\Multiformats\Multibase;

class Proquint{

    /**
     * @param string $binary
     * @param string $alphabetConsonants
     * @param string $alphabetVowels
     * @return string
     */
    public static function decode(string $binary,string $alphabetConsonants,string $alphabetVowels): string{
        if(substr($binary,0,3)!=='ro-'){
            return '';
        }
        $binary = substr($binary,3);
        $data = '';
        foreach(explode('-',$binary) AS $part){
            $short = (strpos($alphabetConsonants,$part[0])&0xF)<<12 | (strpos($alphabetVowels,$part[1])&0x3)<<10 | (strpos($alphabetConsonants,$part[2])&0xF)<<6 | (strpos($alphabetVowels,$part[3])&0x3)<<4 | (strpos($alphabetConsonants,$part[4])&0xF)<<0;
            $data .= chr($short>>8 & 0xFF).chr($short & 0xFF);
        }
        return $data;
    }

    /**
     * @param string $data
     * @param string $alphabetConsonants
     * @param string $alphabetVowels
     * @return string
     */
    public static function encode(string $data,string $alphabetConsonants,string $alphabetVowels): string{
        if(strlen($data)%2!==0){
            return '';
        }
        $shorts = strlen($data)/2;
        $parts = [];
        for($i=0;$i<$shorts;$i++){
            $value = ord($data[$i*2])<<8 | ord($data[$i*2+1]);
            $parts[] = implode([
                $alphabetConsonants[($value>>12) & 0xF],
                $alphabetVowels[($value>>10) & 0x3],
                $alphabetConsonants[($value>>6) & 0xF],
                $alphabetVowels[($value>>4) & 0x3],
                $alphabetConsonants[($value>>0) & 0xF],
            ]);
        }
        return 'ro-'.implode('-',$parts);
    }

}