<?php

use Pace\Model;
use Pace\KeyCollection;
use Pace\XPath\Builder;

class BuilderTest extends PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        Mockery::close();
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testExceptionIsThrownForInvalidOperator()
    {
        (new Builder)->filter('@id', '<>', 99);
    }

    public function testSupportedOperators()
    {
        $this->assertEquals((new Builder)->filter('@id', '=', 99)->toXPath(), '@id = 99');
        $this->assertEquals((new Builder)->filter('@id', '!=', 99)->toXPath(), '@id != 99');
        $this->assertEquals((new Builder)->filter('@id', '>', 99)->toXPath(), '@id > 99');
        $this->assertEquals((new Builder)->filter('@id', '<', 99)->toXPath(), '@id < 99');
        $this->assertEquals((new Builder)->filter('@id', '>=', 99)->toXPath(), '@id >= 99');
        $this->assertEquals((new Builder)->filter('@id', '<=', 99)->toXPath(), '@id <= 99');
    }

    public function testSupportedFunctions()
    {
        $this->assertEquals((new Builder)->contains('@name', 'Smith')->toXPath(), 'contains(@name, "Smith")');
        $this->assertEquals((new Builder)->startsWith('@name', 'John')->toXPath(), 'starts-with(@name, "John")');
    }

    public function testNativeTypeConversion()
    {
        $this->assertEquals((new Builder)->filter('@id', 99)->toXPath(), '@id = 99');
        $this->assertEquals((new Builder)->filter('@actualHours', 0.25)->toXPath(), '@actualHours = 0.25');
        $this->assertEquals((new Builder)->filter('@job', '99999')->toXPath(), '@job = "99999"');
        $this->assertEquals((new Builder)->filter('@active', true)->toXPath(), "@active = 'true'");
        $builder = new Builder;
        $builder->filter('@date', new DateTime('2016-02-01'));
        $this->assertEquals($builder->toXPath(), '@date = date(2016, 2, 1)');
    }

    public function testNestedFilters()
    {
        $builder = new Builder;
        $builder->contains('@name', 'Smith');
        $builder->filter(function ($builder) {
            $builder->startsWith('@name', 'Jane')
                ->orStartsWith('@name', 'John');
        });
        $this->assertEquals(
            $builder->toXPath(),
            'contains(@name, "Smith") and (starts-with(@name, "Jane") or starts-with(@name, "John"))'
        );
    }

    public function testOrFilter()
    {
        $this->assertEquals(
            (new Builder)->filter('@id', 99)
                ->orFilter('@id', 100)
                ->toXPath(),
            '@id = 99 or @id = 100'
        );
    }

    public function testOrContains()
    {
        $this->assertEquals((new Builder)->filter('@id', 99)->orContains('@name', 'Smith')->toXPath(), '@id = 99 or contains(@name, "Smith")');
    }

    public function testSorting()
    {
        $builder = new Builder;
        $builder->sort('@active')->sort('@annualQuota', true);
        $this->assertEquals(
            ['XPathDataSort' => [
                ['xpath' => '@active', 'descending' => false],
                ['xpath' => '@annualQuota', 'descending' => true]
            ]],
            $builder->toXPathSort()
        );
    }

    public function testFindMethod()
    {
        $model = Mockery::mock(Model::class);
        $collection = Mockery::mock(KeyCollection::class);
        $model->shouldReceive('find')->with("@active = 'true'", null)->once()->andReturn($collection);
        $model->shouldReceive('find')
            ->with("@active = 'true'", ['XPathDataSort' => [['xpath' => '@name', 'descending' => false]]])
            ->once()
            ->andReturn($collection);
        $builder = new Builder($model);
        $builder->filter('@active', true);
        $this->assertInstanceOf(KeyCollection::class, $builder->find());
        $builder->sort('@name');
        $this->assertInstanceOf(KeyCollection::class, $builder->find());
    }

    public function testFirstOrNewMethodModelNotFound()
    {
        $model = Mockery::mock(Model::class);
        $collection = Mockery::mock(KeyCollection::class);
        $model->shouldReceive('find')->once()->andReturn($collection);
        $model->shouldReceive('newInstance')->once()->andReturn($model);
        $collection->shouldReceive('first')->once()->andReturnNull();
        $this->assertInstanceOf(Model::class, (new Builder($model))->firstOrNew());
    }

    public function testFirstOrNewMethodModelFound()
    {
        $model = Mockery::mock(Model::class);
        $collection = Mockery::mock(KeyCollection::class);
        $model->shouldReceive('find')->once()->andReturn($collection);
        $collection->shouldReceive('first')->once()->andReturn($model);
        $this->assertInstanceOf(Model::class, (new Builder($model))->firstOrNew());
    }

    public function testFirstOrFailMethod()
    {
        $model = Mockery::mock(Model::class);
        $collection = Mockery::mock(KeyCollection::class);
        $model->shouldReceive('find')->once()->andReturn($collection);
        $collection->shouldReceive('first')->once()->andReturn($model);
        $this->assertInstanceOf(Model::class, (new Builder($model))->firstOrFail());
    }

    /**
     * @expectedException \Pace\ModelNotFoundException
     */
    public function testFirstOrFailMethodThrowsModelNotFoundException()
    {
        $model = Mockery::mock(Model::class);
        $collection = Mockery::mock(KeyCollection::class);
        $model->shouldReceive('find')->once()->andReturn($collection);
        $model->shouldReceive('getType')->once()->andReturn('Job');
        $collection->shouldReceive('first')->once()->andReturnNull();
        (new Builder($model))->firstOrFail();
    }
}
