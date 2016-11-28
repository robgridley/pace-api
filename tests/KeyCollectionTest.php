<?php

use Pace\Model;
use Pace\KeyCollection;

class KeyCollectionTest extends PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        Mockery::close();
    }

    public function testAllMethod()
    {
        $model = Mockery::mock(Model::class);
        $collection = new KeyCollection($model, [1, 2, 3]);
        $model->shouldReceive('read')->times(3)->andReturnSelf();
        $all = $collection->all();
        $this->assertInternalType('array', $all);
        $this->assertContainsOnlyInstancesOf(Model::class, $all);
    }

    public function testCountable()
    {
        $model = Mockery::mock(Model::class);
        $collection = new KeyCollection($model, [1, 2, 3]);
        $this->assertCount(3, $collection);
        $collection = new KeyCollection($model, []);
        $this->assertCount(0, $collection);
    }

    public function testIterator()
    {
        $model = Mockery::mock(Model::class);
        $collection = new KeyCollection($model, [1, 2, 3]);
        $model->shouldReceive('read')->with(1)->once()->andReturnSelf();
        $model->shouldReceive('read')->with(2)->once()->andReturnSelf();
        $model->shouldReceive('read')->with(3)->once()->andReturnSelf();
        $this->assertContainsOnlyInstancesOf(Model::class, $collection);
    }

    public function testDiffMethod()
    {
        $model = Mockery::mock(Model::class);
        $collection = new KeyCollection($model, [1, 2, 3]);
        $diff = $collection->diff([3, 4, 5]);
        $this->assertInstanceOf(KeyCollection::class, $diff);
        $this->assertArrayHasKey(1, $diff);
        $this->assertArrayHasKey(2, $diff);
        $this->assertArrayNotHasKey(3, $diff);
    }

    public function testFirstMethod()
    {
        $model = Mockery::mock(Model::class);
        $collection = new KeyCollection($model, [4, 5, 6]);
        $model->shouldReceive('read')->with(4)->once()->andReturnSelf();
        $this->assertInstanceOf(Model::class, $collection->first());
    }

    public function testLastMethod()
    {
        $model = Mockery::mock(Model::class);
        $collection = new KeyCollection($model, [4, 5, 6]);
        $model->shouldReceive('read')->with(6)->once()->andReturnSelf();
        $this->assertInstanceOf(Model::class, $collection->last());
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testGetMethodThrowsOutOfBoundsException()
    {
        $model = Mockery::mock(Model::class);
        $collection = new KeyCollection($model, [1]);
        $collection->get(2);
    }

    public function testHasMethod()
    {
        $model = Mockery::mock(Model::class);
        $collection = new KeyCollection($model, [4, 5, '6A']);
        $this->assertTrue($collection->has(5));
        $this->assertFalse($collection->has(7));
        $this->assertTrue($collection->has('6A'));
        $this->assertFalse($collection->has(6));
    }

    public function testIsEmptyMethod()
    {
        $model = Mockery::mock(Model::class);
        $collection = new KeyCollection($model, []);
        $this->assertTrue($collection->isEmpty());
        $collection = new KeyCollection($model, [1, 2, 3]);
        $this->assertFalse($collection->isEmpty());
    }

    public function testJsonSerializable()
    {
        $model = Mockery::mock(Model::class);
        $collection = new KeyCollection($model, [5]);
        $model->shouldReceive('read')->with(5)->once()->andReturnSelf();
        $array = $collection->jsonSerialize();
        $this->assertInstanceOf('JsonSerializable', $collection);
        $this->assertInternalType('array', $array);
        $this->assertContainsOnlyInstancesOf('JsonSerializable', $array);
    }

    public function testArrayAccess()
    {
        $model = Mockery::mock(Model::class);
        $collection = new KeyCollection($model, [7, 8, 9]);
        $model->shouldReceive('read')->with(8)->once()->andReturnSelf();
        $this->assertFalse(isset($collection[6]));
        $this->assertTrue(isset($collection[7]));
        $this->assertInstanceOf(Model::class, $collection[8]);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testImmutableSet()
    {
        $model = Mockery::mock(Model::class);
        $collection = new KeyCollection($model, []);
        $collection[0] = 1;
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testImmutableUnset()
    {
        $model = Mockery::mock(Model::class);
        $collection = new KeyCollection($model, [1]);
        unset($collection[1]);
    }

    public function testPaginateMethod()
    {
        $model = Mockery::mock(Model::class);
        $collection = new KeyCollection($model, [1, 2, 3, 4, 5, 6, 7, 8, 9]);
        $paginated = $collection->paginate(1, 5);
        $this->assertInstanceOf(KeyCollection::class, $paginated);
        $this->assertEquals([1, 2, 3, 4, 5], $paginated->keys());
        $paginated = $collection->paginate(2, 5);
        $this->assertEquals([6, 7, 8, 9], $paginated->keys());
    }

    public function testCastToString()
    {
        $model = Mockery::mock(Model::class);
        $collection = new KeyCollection($model, [1]);
        $model->shouldReceive('read')->with(1)->once()->andReturnSelf();
        $model->shouldReceive('jsonSerialize')->once()->andReturn(['id' => 1, 'name' => 'John Smith']);
        $this->assertEquals(json_encode([1 => ['id' => 1, 'name' => 'John Smith']]), $collection);
    }

    public function testReadOnEmptyCollection()
    {
        $model = Mockery::mock(Model::class);
        $collection = new KeyCollection($model, []);
        $this->assertNull($collection->current());
    }

    public function testPluckMethod()
    {
        $model = Mockery::mock(Model::class);
        $collection = new KeyCollection($model, [1, 2]);
        $model->shouldReceive('read')->with(1)->once()->andReturnSelf();
        $model->shouldReceive('read')->with(2)->once()->andReturnSelf();
        $model->shouldReceive('getProperty')->with('value')->once()->andReturn('Test 1');
        $model->shouldReceive('getProperty')->with('value')->once()->andReturn('Test 2');
        $list = $collection->pluck('value');
        $this->assertEquals('Test 1', $list[0]);
        $this->assertEquals('Test 2', $list[1]);
    }

    public function testPluckMethodWithKey()
    {
        $model = Mockery::mock(Model::class);
        $collection = new KeyCollection($model, [1, 2]);
        $model->shouldReceive('read')->with(1)->once()->andReturnSelf();
        $model->shouldReceive('read')->with(2)->once()->andReturnSelf();
        $model->shouldReceive('getProperty')->with('key')->once()->andReturn(3);
        $model->shouldReceive('getProperty')->with('key')->once()->andReturn(4);
        $model->shouldReceive('getProperty')->with('value')->once()->andReturn('Test 1');
        $model->shouldReceive('getProperty')->with('value')->once()->andReturn('Test 2');
        $list = $collection->pluck('value', 'key');
        $this->assertEquals('Test 1', $list[3]);
        $this->assertEquals('Test 2', $list[4]);
    }
}
