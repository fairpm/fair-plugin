<?php
namespace YOCLIB\Multiformats\Multibase;

class Base256Emoji{

    /**
     * @param string $binary
     * @param string $alphabet
     * @return string
     */
    public static function decode(string $binary,string $alphabet): string{
        $data = '';
        foreach(mb_str_split($binary) ?: [] AS $c){
            $data .= chr(array_search($c,mb_str_split($alphabet),true));
        }
        return $data;
    }

    /**
     * @param string $data
     * @param string $alphabet
     * @return string
     */
    public static function encode(string $data,string $alphabet): string{
        $binary = '';
        for($i=0;$i<strlen($data);$i++){
            $binary .= mb_str_split($alphabet)[ord($data[$i])];
        }
        return $binary;
    }

}