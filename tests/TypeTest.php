<?php

use Pace\Type;

class TypeTest extends PHPUnit_Framework_TestCase
{
    public function testCamelize()
    {
        $this->assertEquals('csr', Type::camelize('CSR'));
        $this->assertEquals('jobPart', Type::camelize('JobPart'));
    }

    public function testModelify()
    {
        $this->assertEquals('GLBatch', Type::modelify('glBatch'));
        $this->assertEquals('SalesPerson', Type::modelify('salesPerson'));
    }

    public function testSingularize()
    {
        $this->assertEquals('jobStatus', Type::singularize('jobStatus'));
        $this->assertEquals('jobStatus', Type::singularize('jobStatuses'));
    }
}
