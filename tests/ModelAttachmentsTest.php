<?php

use Pace\Model;
use Pace\Client;
use Pace\Services\AttachmentService;

class ModelAttachmentsTest extends PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        Mockery::close();
    }

    /**
     * @expectedException \BadMethodCallException
     */
    public function testGetContentOnlyAvailableOnFileAttachment()
    {
        $client = Mockery::mock(Client::class);
        $model = new Model($client, 'Job');
        $model->getContent();
    }

    public function testGetContent()
    {
        $client = Mockery::mock(Client::class);
        $service = Mockery::mock(AttachmentService::class);
        $client->shouldReceive('attachment')->andReturn($service);
        $service->shouldReceive('getByKey')->with('abcd1234')->andReturn([
            'content' => 'Some random string',
        ]);
        $model = new Model($client, 'FileAttachment', ['attachment' => 'abcd1234']);
        $this->assertEquals('Some random string', $model->getContent());
    }

    public function testAttachFileMethod()
    {
        $client = Mockery::mock(Client::class);
        $service = Mockery::mock(AttachmentService::class);
        $client->shouldReceive('attachment')->andReturn($service);
        $service->shouldReceive('add')
            ->with('Company', '001', 'logo', 'logo.png', 'SomeBinaryData')
            ->andReturn('abcd1234');
        $fileAttachment = Mockery::mock(Model::class);
        $client->shouldReceive('model')->with('FileAttachment')->andReturn($fileAttachment);
        $fileAttachment->shouldReceive('read')->with('abcd1234')->andReturn($fileAttachment);
        $model = new Model($client, 'Company', ['id' => '001']);
        $this->assertEquals($fileAttachment, $model->attachFile('logo.png', 'SomeBinaryData', 'logo'));
    }
}
