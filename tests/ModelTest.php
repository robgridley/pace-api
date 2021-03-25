<?php

use Pace\Model;
use Pace\Client;
use Pace\KeyCollection;
use Pace\XPath\Builder;

class ModelTest extends PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        Mockery::close();
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testCannotBeConstructedWithCamelCase()
    {
        $client = Mockery::mock(Client::class);
        new Model($client, 'salesPerson');
    }

    public function testManipulatesProperties()
    {
        $client = Mockery::mock(Client::class);
        $model = new Model($client, 'CSR');
        $model->name = 'John Smith';
        $this->assertTrue(isset($model->name));
        $this->assertEquals($model->name, 'John Smith');
        unset($model->name);
        $this->assertFalse(isset($model->name));
    }

    public function testPrimaryKeysCanBeSpecifiedOrGuessed()
    {
        $client = Mockery::mock(Client::class);
        $model = new Model($client, 'Bar');
        $model->primaryKey = 9;
        $model->id = 99;
        $model->foo = 999;
        $model->bar = '99999';
        $this->assertEquals($model->key(), 9);
        unset($model->primaryKey);
        $this->assertEquals($model->key(), 99);
        unset($model->id);
        $this->assertEquals($model->key(), '99999');
        $this->assertEquals($model->key('foo'), 999);
    }

    /**
     * @expectedException UnexpectedValueException
     */
    public function testExceptionIsThrownForEmptyKey()
    {
        $client = Mockery::mock(Client::class);
        $model = new Model($client, 'CSR');
        $model->id = 0;
        $model->key();
    }

    public function testRead()
    {
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('readObject')->with('CSR', 3)->once()->andReturn(['id' => 3]);
        $model = new Model($client, 'CSR');
        $read = $model->read(3);
        $this->assertInstanceOf(Model::class, $read);
        $this->assertEquals(3, $read->id);
        $this->assertTrue($read->exists);
    }

    public function testReadWithNullKey()
    {
        $client = Mockery::mock(Client::class);
        $model = new Model($client, 'CSR');
        $this->assertNull($model->read(null));
    }

    public function testReadOrFail()
    {
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('readObject')
            ->once()
            ->with('SalesPerson', 5)
            ->andReturn(['id' => 5, 'name' => 'John Smith']);
        $model = new Model($client, 'SalesPerson');
        $this->assertInstanceOf(Model::class, $model->readOrFail(5));
    }

    /**
     * @expectedException Pace\ModelNotFoundException
     */
    public function testReadOrFailThrowsModelNotFoundException()
    {
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('readObject')->once()->with('SalesPerson', 5)->andReturn(null);
        $model = new Model($client, 'SalesPerson');
        $model->readOrFail(5);
    }

    public function testBelongsTo()
    {
        $client = Mockery::mock(Client::class);
        $model = new Model($client, 'Job');
        $related = Mockery::mock(Model::class);
        $client->shouldReceive('model')->with('CSR')->andReturn($related);
        $model->csr = 5;
        $related->shouldReceive('read')->with(5)->andReturnSelf();
        $this->assertEquals($related, $model->belongsTo('CSR', 'csr'));
    }

    public function testBelongsToWithCompoundKey()
    {
        $client = Mockery::mock(Client::class);
        $model = new Model($client, 'JobMaterial');
        $related = Mockery::mock(Model::class);
        $client->shouldReceive('model')->with('JobPart')->andReturn($related);
        $model->job = '12345';
        $model->jobPart = '01';
        $related->shouldReceive('read')->with('12345:01')->andReturnSelf();
        $this->assertEquals($related, $model->belongsTo('JobPart', 'job:jobPart'));
    }

    public function testHasMany()
    {
        $client = Mockery::mock(Client::class);
        $model = new Model($client, 'Job');
        $model->job = '12345';
        $related = Mockery::mock(Model::class);
        $client->shouldReceive('model')->with('JobPart')->once()->andReturn($related);
        $builder = $model->hasMany('JobPart', 'job');
        $this->assertInstanceOf(Builder::class, $builder);
        $collection = Mockery::mock(KeyCollection::class);
        $related->shouldReceive('find')->with('@job = "12345"', null)->once()->andReturn($collection);
        $this->assertInstanceOf(KeyCollection::class, $builder->get());
    }

    public function testHasManyWithCompoundKey()
    {
        $client = Mockery::mock(Client::class);
        $model = new Model($client, 'JobPart');
        $model->primaryKey = '12345:01';
        $related = Mockery::mock(Model::class);
        $client->shouldReceive('model')->with('JobMaterial')->once()->andReturn($related);
        $builder = $model->hasMany('JobMaterial', 'job:jobPart');
        $this->assertInstanceOf(Builder::class, $builder);
        $collection = Mockery::mock(KeyCollection::class);
        $related->shouldReceive('find')
            ->with('@job = "12345" and @jobPart = "01"', null)
            ->once()
            ->andReturn($collection);
        $this->assertInstanceOf(KeyCollection::class, $builder->get());
    }

    public function testMorphMany()
    {
        $client = Mockery::mock(Client::class);
        $model = new Model($client, 'Job');
        $model->job = '12345';
        $related = Mockery::mock(Model::class);
        $client->shouldReceive('model')->with('FileAttachment')->once()->andReturn($related);
        $builder = $model->morphMany('FileAttachment');
        $this->assertInstanceOf(Builder::class, $builder);
        $collection = Mockery::mock(KeyCollection::class);
        $related->shouldReceive('find')
            ->with('@baseObject = "Job" and @baseObjectKey = "12345"', null)
            ->once()
            ->andReturn($collection);
        $this->assertInstanceOf(KeyCollection::class, $builder->get());
    }

    public function testIsDirty()
    {
        $client = Mockery::mock(Client::class);
        $model = new Model($client, 'CSR', ['name' => 'John Smith']);
        $this->assertFalse($model->isDirty());
        $model->name = 'Jane Smith';
        $this->assertTrue($model->isDirty());
    }

    public function testJoinKeys()
    {
        $client = Mockery::mock(Client::class);
        $model = new Model($client, 'CSR');
        $this->assertEquals('12345:01', $model->joinKeys(['12345', '01']));
    }

    public function testJsonSerializable()
    {
        $client = Mockery::mock(Client::class);
        $model = new Model($client, 'CSR');
        $this->assertInstanceOf(JsonSerializable::class, $model);
    }

    public function testArrayable()
    {
        $client = Mockery::mock(Client::class);
        $attributes = [
            'id' => 1,
            'name' => 'John Smith',
            'email' => 'jsmith@printcompany.com'
        ];
        $model = new Model($client, 'CSR', $attributes);
        $this->assertEquals($attributes, $model->toArray());
    }

    public function testSaveOnExistingModel()
    {
        $client = Mockery::mock(Client::class);
        $attributes = [
            'id' => 1,
            'name' => 'John Smith',
            'email' => 'jsmith@printcompany.com'
        ];
        $response = $attributes;
        $client->shouldReceive('updateObject')->with('CSR', $attributes)->once()->andReturn($response);
        $model = new Model($client, 'CSR', $attributes);
        $model->exists = true;
        $this->assertTrue($model->save());
        $this->assertFalse($model->isDirty());
    }

    public function testSaveOnNewModel()
    {
        $client = Mockery::mock(Client::class);
        $attributes = [
            'name' => 'John Smith',
            'email' => 'jsmith@printcompany.com'
        ];
        $response = $attributes;
        $response['id'] = 2;
        $client->shouldReceive('createObject')
            ->with('CSR', Mockery::mustBe($attributes))
            ->once()
            ->andReturn($response);
        $model = new Model($client, 'CSR');
        $model->name = 'John Smith';
        $model->email = 'jsmith@printcompany.com';
        $this->assertTrue($model->save());
        $this->assertTrue($model->exists);
        $this->assertEquals(2, $model->id);
        $this->assertFalse($model->isDirty());
    }

    public function testCreate()
    {
        $client = Mockery::mock(Client::class);
        $attributes = [
            'name' => 'John Smith',
            'email' => 'jsmith@printcompany.com'
        ];
        $response = $attributes;
        $response['id'] = 2;
        $client->shouldReceive('createObject')
            ->with('CSR', Mockery::mustBe($attributes))
            ->once()
            ->andReturn($response);
        $model = new Model($client, 'CSR');
        $this->assertInstanceOf(Model::class, $model->create($attributes));
    }

    public function testSplitKey()
    {
        $client = Mockery::mock(Client::class);
        $model = new Model($client, 'JobPart', ['primaryKey' => '12345:01']);
        $this->assertEquals(['12345', '01'], $model->splitKey());
        $this->assertEquals(['job', 'jobPart'], $model->splitKey('job:jobPart'));
    }

    public function testGetDirty()
    {
        $client = Mockery::mock(Client::class);
        $model = new Model($client, 'CSR', ['name' => 'John Smith', 'email' => 'jsmith@printcompany.com']);
        $model->email = 'john.smith@printcompany.com';
        $this->assertEquals(['email' => 'john.smith@printcompany.com'], $model->getDirty());
    }

    public function testDuplicate()
    {
        $client = Mockery::mock(Client::class);
        $attributes = [
            'id' => 5001,
            'description' => 'Ground',
            'provider' => 5001
        ];
        $client->shouldReceive('cloneObject')
            ->with(
                'ShipVia',
                Mockery::mustBe($attributes),
                Mockery::mustBe(['description' => 'Express']),
                null
            )
            ->once()
            ->andReturn(['id' => 5002, 'description' => 'Express', 'provider' => 5001]);
        $model = new Model($client, 'ShipVia', $attributes);
        $model->exists = true;
        $model->description = 'Express';
        $duplicate = $model->duplicate();
        $this->assertEquals('Ground', $model->description);
        $this->assertEquals('Express', $duplicate->description);
        $this->assertEquals(5002, $duplicate->id);
        $this->assertTrue($duplicate->exists);
        $model->exists = false;
        $this->assertNull($model->duplicate());
    }

    public function testDuplicateWithNewKey()
    {
        $client = Mockery::mock(Client::class);
        $attributes = [
            'id' => 5001,
            'description' => 'Ground',
            'provider' => 5001
        ];
        $client->shouldReceive('cloneObject')
            ->with(
                'ShipVia',
                Mockery::mustBe($attributes),
                Mockery::mustBe(['description' => 'Express']),
                5002
            )
            ->once()
            ->andReturn(['id' => 5002, 'description' => 'Express', 'provider' => 5001]);
        $model = new Model($client, 'ShipVia', $attributes);
        $model->exists = true;
        $model->description = 'Express';
        $model->duplicate(5002);
    }

    public function testMagicBelongsTo()
    {
        $client = Mockery::mock(Client::class);
        $model = new Model($client, 'Job');
        $model->csr = 5;
        $related = Mockery::mock(Model::class);
        $client->shouldReceive('model')->with('CSR')->andReturn($related);
        $related->shouldReceive('read')->with(5)->andReturnSelf();
        $this->assertEquals($related, $model->csr());
    }

    public function testMagicHasMany()
    {
        $client = Mockery::mock(Client::class);
        $model = new Model($client, 'Job');
        $model->job = '12345';
        $related = Mockery::mock(Model::class)->shouldAllowMockingProtectedMethods();
        $builder = Mockery::mock(Builder::class);
        $client->shouldReceive('model')->with('JobPart')->once()->andReturn($related);
        $related->shouldReceive('newBuilder')->once()->andReturn($builder);
        $builder->shouldReceive('filter')->with('@job', '12345')->once()->andReturn($builder);
        $this->assertEquals($builder, $model->jobParts());
    }

    public function testCallBuilder()
    {
        $client = Mockery::mock(Client::class);
        $model = new Model($client, 'Job');
        $builder = $model->filter('@active', true);
        $this->assertInstanceOf(Builder::class, $builder);
    }

    public function testGetType()
    {
        $client = Mockery::mock(Client::class);
        $model = new Model($client, 'GLAccount');
        $this->assertEquals('GLAccount', $model->getType());
    }

    public function testCastToString()
    {
        $client = Mockery::mock(Client::class);
        $attributes = ['id' => 1, 'name' => 'John Smith'];
        $model = new Model($client, 'CSR', $attributes);
        $this->assertEquals(json_encode($attributes), $model);
    }

    public function testDelete()
    {
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('deleteObject')->with('CSR', 3)->once()->andReturnNull();
        $model = new Model($client, 'CSR');
        $model->id = 3;
        $model->exists = true;
        $this->assertTrue($model->delete());
        $this->assertFalse($model->exists);
        $this->assertNull($model->delete());
    }

    public function testFresh()
    {
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('readObject')
            ->with('CSR', 3)
            ->once()
            ->andReturn(['id' => 3, 'name' => 'John Smith']);
        $model = new Model($client, 'CSR');
        $model->id = 3;
        $model->exists = true;
        $fresh = $model->fresh();
        $this->assertInstanceOf(Model::class, $fresh);
        $this->assertEquals('John Smith', $fresh->name);
        $this->assertTrue($fresh->exists);
        $model->exists = false;
        $this->assertNull($model->fresh());
    }

    public function testFind()
    {
        $client = Mockery::mock(Client::class);
        $model = new Model($client, 'CSR');
        $client->shouldReceive('findObjects')
            ->with('CSR', "@active = 'true'", null)
            ->once()
            ->andReturn([1, 4, 9]);
        $keys = $model->find("@active = 'true'");
        $this->assertInstanceOf(KeyCollection::class, $keys);
        $this->assertCount(3, $keys);
        $this->assertTrue($keys->has(9));
    }

    public function testArrayAccess()
    {
        $client = Mockery::mock(Client::class);
        $model = new Model($client, 'CSR');
        $this->assertFalse(isset($model['name']));
        $model['name'] = 'John Smith';
        $this->assertEquals('John Smith', $model['name']);
        $this->assertTrue(isset($model['name']));
        unset($model['name']);
        $this->assertFalse(isset($model['name']));
    }

    public function testSetRelatedModelAsValue()
    {
        $client = Mockery::mock(Client::class);
        $model = new Model($client, 'Job');
        $related = new Model($client, 'CSR');
        $related->id = 3;
        $model->csr = $related;
        $this->assertEquals(3, $model->csr);
    }
}
