<?php
namespace YOCLIB\Multiformats\Multibase\Tests;

use PHPUnit\Framework\TestCase;

use YOCLIB\Multiformats\Multibase\Multibase;

class TestUtil{

    public static function getCSVData($filename): array{
        return array_map('str_getcsv',file($filename));
    }

    /**
     * @param TestCase $test
     * @param $csv
     * @param bool $encode
     * @param bool $decode
     */
    public static function runTest(TestCase $test,$csv,bool $encode=true,bool $decode=true){
        $data = str_replace('\x00',"\0",$csv[0][1]);
        for($i=1;$i<count($csv);$i++){
            $encoding = $csv[$i][0];
            $vector = $csv[$i][1];

            $constantName = Multibase::class.'::'.strtoupper($encoding);

            if($encode){
                $test->assertEquals($vector,Multibase::encode(constant($constantName),$data),'Encoding '.$encoding);
            }
            if($decode){
                $test->assertEquals($data,Multibase::decode($vector),'Decoding '.$encoding);
            }
        }
    }

}