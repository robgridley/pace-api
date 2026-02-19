<?php

use Pace\KeyCollection;
use Pace\Model;
use Pace\ModelNotFoundException;
use Pace\XPath\Builder;
use PHPUnit\Framework\TestCase;

class BuilderTest extends TestCase
{
    public function tearDown(): void
    {
        Mockery::close();
    }

    public function testExceptionIsThrownForInvalidOperator()
    {
        $this->expectException(InvalidArgumentException::class);
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

    public function testInFilter()
    {
        $builder = new Builder;
        $builder->in('@id', [1, 2, 5, 10]);
        $this->assertEquals($builder->toXPath(), '(@id = 1 or @id = 2 or @id = 5 or @id = 10)');
    }

    public function testOrInFilter()
    {
        $builder = new Builder;
        $builder->filter('@id', '<', 5)->in('@id', [1, 2, 5, 10]);
        $this->assertEquals($builder->toXPath(), '@id < 5 and (@id = 1 or @id = 2 or @id = 5 or @id = 10)');
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

    public function testLoadingFields()
    {
        $builder = new Builder();
        $builder->load([
            '@description',
            'description_2' => '@description2',
        ]);
        $this->assertEquals(
            [
                [
                    'name' => 'description',
                    'xpath' => '@description',
                ],
                [
                    'name' => 'description_2',
                    'xpath' => '@description2',
                ],
            ],
            $builder->toFieldDescriptor()
        );
    }

    public function testFind()
    {
        $model = Mockery::mock(Model::class);
        $collection = Mockery::mock(KeyCollection::class);
        $model->shouldReceive('find')
            ->with("@active = 'true'", null, 0, null, [])
            ->once()
            ->andReturn($collection);
        $model->shouldReceive('find')
            ->with("@active = 'true'", ['XPathDataSort' => [['xpath' => '@name', 'descending' => false]]], 0, null, [])
            ->once()
            ->andReturn($collection);
        $builder = new Builder($model);
        $builder->filter('@active', true);
        $this->assertInstanceOf(KeyCollection::class, $builder->find());
        $builder->sort('@name');
        $this->assertInstanceOf(KeyCollection::class, $builder->find());
    }

    public function testFirstOrNewModelNotFound()
    {
        $model = Mockery::mock(Model::class);
        $collection = Mockery::mock(KeyCollection::class);
        $model->shouldReceive('find')->once()->andReturn($collection);
        $model->shouldReceive('newInstance')->once()->andReturn($model);
        $collection->shouldReceive('first')->once()->andReturnNull();
        $this->assertInstanceOf(Model::class, (new Builder($model))->firstOrNew());
    }

    public function testFirstOrNewModelFound()
    {
        $model = Mockery::mock(Model::class);
        $collection = Mockery::mock(KeyCollection::class);
        $model->shouldReceive('find')->once()->andReturn($collection);
        $collection->shouldReceive('first')->once()->andReturn($model);
        $this->assertInstanceOf(Model::class, (new Builder($model))->firstOrNew());
    }

    public function testFirstOrFail()
    {
        $model = Mockery::mock(Model::class);
        $collection = Mockery::mock(KeyCollection::class);
        $model->shouldReceive('find')->once()->andReturn($collection);
        $collection->shouldReceive('first')->once()->andReturn($model);
        $this->assertInstanceOf(Model::class, (new Builder($model))->firstOrFail());
    }

    public function testFirstOrFailThrowsModelNotFoundException()
    {
        $model = Mockery::mock(Model::class);
        $collection = Mockery::mock(KeyCollection::class);
        $model->shouldReceive('find')->once()->andReturn($collection);
        $model->shouldReceive('getType')->once()->andReturn('Job');
        $collection->shouldReceive('first')->once()->andReturnNull();
        $this->expectException(ModelNotFoundException::class);
        (new Builder($model))->firstOrFail();
    }
}
