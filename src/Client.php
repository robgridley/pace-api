<?php

namespace Pace;

use Closure;
use InvalidArgumentException;
use Pace\Contracts\Soap\Factory as SoapFactory;
use Pace\InvokeAction\InvokeActionRequest;
use Pace\Report\Builder as ReportBuilder;
use Pace\Services\AttachmentService;
use Pace\Soap\DateTimeMapping;

class Client
{
    /**
     * The primary key field.
     */
    const PRIMARY_KEY = 'primaryKey';

    /**
     * Previously loaded services.
     *
     * @var array
     */
    protected array $services = [];

    /**
     * The SOAP client factory.
     *
     * @var SoapFactory
     */
    protected SoapFactory $soapFactory;

    /**
     * The Pace services URL.
     *
     * @var string
     */
    protected string $url;

    /**
     * Create a new instance.
     *
     * @param SoapFactory $soapFactory
     * @param string $host
     * @param string $login
     * @param string $password
     * @param string $scheme
     */
    public function __construct(SoapFactory $soapFactory, string $host, string $login, string $password, string $scheme = 'https')
    {
        $soapFactory->setOptions(compact('login', 'password'));
        $soapFactory->addTypeMapping(new DateTimeMapping);
        $this->soapFactory = $soapFactory;

        $this->url = sprintf('%s://%s/rpc/services/', $scheme, $host);
    }

    /**
     * Prepare the instance for serialization.
     *
     * @return string[]
     */
    public function __sleep(): array
    {
        return ['soapFactory', 'url'];
    }

    /**
     * Dynamically retrieve the specified model.
     *
     * @param string $name
     * @return Model
     */
    public function __get(string $name): Model
    {
        return $this->model(Type::modelify($name));
    }

    /**
     * Get an instance of the attachment service.
     *
     * @return AttachmentService
     */
    public function attachment(): AttachmentService
    {
        return $this->service('AttachmentService');
    }

    /**
     * Clone an object.
     *
     * @param string $object
     * @param array $attributes
     * @param array $newAttributes
     * @param int|string|null $newKey
     * @param array|null $newParent
     * @return array
     */
    public function cloneObject(string $object, array $attributes, array $newAttributes, mixed $newKey = null, ?array $newParent = null): array
    {
        return $this->service('CloneObject')->clone($object, $attributes, $newAttributes, $newKey, $newParent);
    }

    /**
     * Create an object.
     *
     * @param string $object
     * @param array $attributes
     * @return array
     */
    public function createObject(string $object, array $attributes): array
    {
        return $this->service('CreateObject')->create($object, $attributes);
    }

    /**
     * Delete an object by its primary key.
     *
     * @param string $object
     * @param int|string $key
     */
    public function deleteObject(string $object, mixed $key): void
    {
        $this->service('DeleteObject')->delete($object, $key);
    }

    /**
     * Find objects.
     *
     * @param string $object
     * @param string $filter
     * @param array|null $sort
     * @return array
     */
    public function findObjects(string $object, string $filter, ?array $sort = null): array
    {
        if (is_null($sort)) {
            return $this->service('FindObjects')->find($object, $filter);
        }

        return $this->service('FindObjects')->findAndSort($object, $filter, $sort);
    }

    /**
     * Get a model instance.
     *
     * @param string $type
     * @return Model
     */
    public function model(string $type): Model
    {
        return new Model($this, $type);
    }

    /**
     * Read an object by its primary key.
     *
     * @param string $object
     * @param int|string $key
     * @return array|null
     */
    public function readObject(string $object, mixed $key): ?array
    {
        return $this->service('ReadObject')->read($object, $key);
    }

    /**
     * Run a report.
     *
     * @param Model|int $report
     * @return ReportBuilder
     */
    public function report(Model|int $report): ReportBuilder
    {
        if (!$report instanceof Model) {
            $report = $this->model('Report')->readOrFail($report);
        }

        return new ReportBuilder($this->service('ReportService'), $report);
    }

    /**
     * Get an invoke action request instance.
     *
     * @return InvokeActionRequest
     */
    public function invokeAction(): InvokeActionRequest
    {
        return new InvokeActionRequest($this, $this->service('InvokeAction'));
    }

    /**
     * Get an instance of the specified service.
     *
     * @param string $name
     * @return mixed
     */
    public function service(string $name): mixed
    {
        return $this->services[$name] ?? $this->services[$name] = $this->makeService($name);
    }

    /**
     * Wrap the specified closure in a transaction.
     *
     * @param Closure $callback
     */
    public function transaction(Closure $callback): void
    {
        $this->service('TransactionService')->transaction($callback);
    }

    /**
     * Start a transaction.
     *
     * @param int $timeout
     */
    public function startTransaction(int $timeout = 60): void
    {
        $this->service('TransactionService')->startTransaction($timeout);
    }

    /**
     * Rollback the transaction.
     */
    public function rollbackTransaction(): void
    {
        $this->service('TransactionService')->rollback();
    }

    /**
     * Commit the transaction.
     */
    public function commitTransaction(): void
    {
        $this->service('TransactionService')->commit();
    }

    /**
     * Update an object.
     *
     * @param string $object
     * @param array $attributes
     * @return array
     */
    public function updateObject(string $object, array $attributes): array
    {
        return $this->service('UpdateObject')->update($object, $attributes);
    }

    /**
     * Determine the version of Pace running on the server.
     *
     * @return array
     */
    public function version(): array
    {
        return $this->service('Version')->get();
    }

    /**
     * Assemble the specified service's WSDL.
     *
     * @param string $service
     * @return string
     */
    protected function getServiceWsdl(string $service): string
    {
        return $this->url . $service . '?wsdl';
    }

    /**
     * Create a new instance of the specified service.
     *
     * @param string $service
     * @return mixed
     */
    protected function makeService(string $service): mixed
    {
        $class = 'Pace\\Services\\' . $service;

        if (!class_exists($class)) {
            throw new InvalidArgumentException('Service [$service] is not implemented');
        }

        $soap = $this->soapFactory->make($this->getServiceWsdl($service));

        return new $class($soap);
    }
}
