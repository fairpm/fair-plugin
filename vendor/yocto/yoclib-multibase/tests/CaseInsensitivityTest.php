<?php
namespace YOCLIB\Multiformats\Multibase\Tests;

use PHPUnit\Framework\TestCase;

class CaseInsensitivityTest extends TestCase{

    public function testCSV(){
        $csv = TestUtil::getCSVData('tests/case_insensitivity.csv');
        TestUtil::runTest($this,$csv,false);
    }

}