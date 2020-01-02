<?php

use Pace\Model;
use Pace\KeyCollection;
use Pace\Report\Builder;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Pace\Services\ReportService;
use Pace\XPath\Builder as XPathBuilder;

class ReportBuilderTest extends TestCase
{
    public function tearDown(): void
    {
        Mockery::close();
    }

    public function testParameter(): void
    {
        $builder = $this->newBuilder();
        $builder->parameter(1234, 'TestValue');
        $builder->parameter(1235, 99);
        $this->assertEquals([
            'baseObjectKey' => null,
            'reportId' => 123,
            'reportParameterWrappers' => [
                [
                    'reportParameterId' => 1234,
                    'value' => 'TestValue',
                ],
                [
                    'reportParameterId' => 1235,
                    'value' => 99,
                ],
            ],
        ], $builder->toWrapper());
    }

    public function testBaseObjectKey(): void
    {
        $builder = $this->newBuilder();
        $builder->baseObjectKey('99999');
        $this->assertEquals([
            'baseObjectKey' => '99999',
            'reportId' => 123,
            'reportParameterWrappers' => [],
        ], $builder->toWrapper());
    }

    public function testNamedParameter(): void
    {
        $report = Mockery::mock(Model::class);
        $report->shouldReceive('key')->once()->andReturn(123);
        $xpath = Mockery::mock(XPathBuilder::class);
        $report->shouldReceive('reportParameters')->andReturn($xpath);
        $xpath->shouldReceive('filter')->with('@name', 'Test Parameter')->andReturn($xpath);
        $collection = Mockery::mock(KeyCollection::class);
        $collection->shouldReceive('key')->andReturn(1236);
        $xpath->shouldReceive('get')->andReturn($collection);
        $builder = $this->newBuilder(null, $report);
        $builder->namedParameter('Test Parameter', '2020-01-01');
        $this->assertEquals([
            'baseObjectKey' => null,
            'reportId' => 123,
            'reportParameterWrappers' => [
                [
                    'reportParameterId' => 1236,
                    'value' => '2020-01-01',
                ],
            ],
        ], $builder->toWrapper());
    }

    public function testNamedParameterThrowsInvalidArgumentException(): void
    {
        $report = Mockery::mock(Model::class);
        $xpath = Mockery::mock(XPathBuilder::class);
        $report->shouldReceive('reportParameters')->andReturn($xpath);
        $xpath->shouldReceive('filter')->with('@name', 'Test Parameter')->andReturn($xpath);
        $collection = Mockery::mock(KeyCollection::class);
        $collection->shouldReceive('key')->andReturn(false);
        $xpath->shouldReceive('get')->andReturn($collection);
        $builder = $this->newBuilder(null, $report);
        $this->expectException(InvalidArgumentException::class);
        $builder->namedParameter('Test Parameter', '2020-01-01');
    }

    private function newBuilder(MockInterface $service = null, MockInterface $report = null): Builder
    {
        if (is_null($report)) {
            $report = Mockery::mock(Model::class);
            $report->shouldReceive('key')->once()->andReturn(123);
        }

        return new Builder($service ?: Mockery::mock(ReportService::class), $report);
    }
}
