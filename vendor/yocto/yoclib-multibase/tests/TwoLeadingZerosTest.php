<?php
namespace YOCLIB\Multiformats\Multibase\Tests;

use PHPUnit\Framework\TestCase;

class TwoLeadingZerosTest extends TestCase{

    public function testCSV(){
        $csv = TestUtil::getCSVData('tests/two_leading_zeros.csv');
        TestUtil::runTest($this,$csv);
    }

}