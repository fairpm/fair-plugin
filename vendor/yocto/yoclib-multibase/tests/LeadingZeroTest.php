<?php
namespace YOCLIB\Multiformats\Multibase\Tests;

use PHPUnit\Framework\TestCase;

class LeadingZeroTest extends TestCase{

    public function testCSV(){
        $csv = TestUtil::getCSVData('tests/leading_zero.csv');
        TestUtil::runTest($this,$csv);
    }

}