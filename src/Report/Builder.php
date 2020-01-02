<?php

namespace Pace\Report;

use Pace\Model;
use InvalidArgumentException;
use Pace\Enum\ReportExportType;
use Pace\Services\ReportService;

class Builder
{
    /**
     * The report service.
     *
     * @var ReportService
     */
    protected $service;

    /**
     * The report model.
     *
     * @var Model
     */
    protected $report;

    /**
     * The base object key (if applicable).
     *
     * @var null
     */
    protected $baseObjectKey = null;

    /**
     * The report parameters.
     *
     * @var array
     */
    protected $parameters = [];

    /**
     * The report export media types.
     *
     * @var array
     */
    protected $mediaTypes = [
        ReportExportType::PDF => 'application/pdf',
        ReportExportType::RTF => 'text/rtf',
        ReportExportType::HTML => 'text/html',
        ReportExportType::CSV => 'text/csv',
        ReportExportType::XLS => 'application/vnd.ms-excel',
        ReportExportType::XLSX => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ReportExportType::XML => 'application/xml',
        ReportExportType::TXT => 'text/plain',
    ];

    /**
     * Create a new report builder instance.
     *
     * @param ReportService $service
     * @param Model $report
     */
    public function __construct(ReportService $service, Model $report)
    {
        $this->service = $service;
        $this->report = $report;
    }

    /**
     * Set the specified parameter.
     *
     * @param int $id
     * @param mixed $value
     * @return $this
     */
    public function parameter(int $id, $value): self
    {
        $this->parameters[$id] = $value;

        return $this;
    }

    /**
     * Set the specified parameter by looking up its name.
     *
     * @param string $name
     * @param mixed $value
     * @return $this
     */
    public function namedParameter(string $name, $value): self
    {
        $id = $this->report->reportParameters()->filter('@name', $name)->get()->key();

        if (false === $id) {
            throw new InvalidArgumentException("Parameter [$name] does not exist");
        }

        return $this->parameter($id, $value);
    }

    /**
     * Bulk set parameters.
     *
     * @param array $parameters
     * @return $this
     */
    public function parameters(array $parameters): self
    {
        foreach ($parameters as $id => $value) {
            $this->parameter($id, $value);
        }

        return $this;
    }

    /**
     * Set the base object key.
     *
     * @param mixed $key
     * @return $this
     */
    public function baseObjectKey($key): self
    {
        $this->baseObjectKey = $key instanceof Model ? $key->key() : $key;

        return $this;
    }

    /**
     * Run the report and get the file.
     *
     * @return File
     */
    public function get(): File
    {
        $report = $this->service->executeReport($this->toWrapper());

        return File::fromBase64($report['content'], $this->mediaTypes[$this->report->exportType] ?? null);
    }

    /**
     * Print the report.
     */
    public function print(): void
    {
        $this->service->printReport($this->toWrapper());
    }

    /**
     * Convert the instance to a report wrapper.
     *
     * @return array
     */
    public function toWrapper(): array
    {
        $parameters = [];

        foreach ($this->parameters as $id => $value) {
            $parameters[] = [
                'reportParameterId' => $id,
                'value' => $value,
            ];
        }

        return [
            'baseObjectKey' => $this->baseObjectKey,
            'reportId' => $this->report->key(),
            'reportParameterWrappers' => $parameters,
        ];
    }
}
