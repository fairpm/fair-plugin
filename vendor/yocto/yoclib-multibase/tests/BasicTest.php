<?php
namespace YOCLIB\Multiformats\Multibase\Tests;

use PHPUnit\Framework\TestCase;

class BasicTest extends TestCase{

    public function testCSV(){
        $csv = TestUtil::getCSVData('tests/basic.csv');
        TestUtil::runTest($this,$csv);
    }

}