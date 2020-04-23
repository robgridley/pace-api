<?php

namespace Pace;

use Closure;
use InvalidArgumentException;
use Pace\Soap\DateTimeMapping;
use Pace\Report\Builder as ReportBuilder;
use Pace\Contracts\Soap\Factory as SoapFactory;

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
    protected $services = [];

    /**
     * The SOAP client factory.
     *
     * @var SoapFactory
     */
    protected $soapFactory;

    /**
     * The Pace services URL.
     *
     * @var string
     */
    protected $url;

    /**
     * Create a new instance.
     *
     * @param SoapFactory $soapFactory
     * @param string $host
     * @param string $login
     * @param string $password
     * @param string $scheme
     */
    public function __construct(SoapFactory $soapFactory, $host, $login, $password, $scheme = 'https')
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
    public function __sleep()
    {
        return ['soapFactory', 'url'];
    }

    /**
     * Dynamically retrieve the specified model.
     *
     * @param string $name
     * @return Model
     */
    public function __get($name)
    {
        return $this->model(Type::modelify($name));
    }

    /**
     * Get an instance of the attachment service.
     *
     * @return \Pace\Services\AttachmentService
     */
    public function attachment()
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
    public function cloneObject($object, array $attributes, array $newAttributes, $newKey = null, array $newParent = null)
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
    public function createObject($object, array $attributes)
    {
        return $this->service('CreateObject')->create($object, $attributes);
    }

    /**
     * Delete an object by its primary key.
     *
     * @param string $object
     * @param int|string $key
     */
    public function deleteObject($object, $key)
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
    public function findObjects($object, $filter, array $sort = null)
    {
        if (is_null($sort)) {
            return $this->service('FindObjects')->find($object, $filter);
        }

        return $this->service('FindObjects')->findAndSort($object, $filter, $sort);
    }

    /**
     * Get a model instance.
     *
     * @param Type|string $type
     * @return Model
     */
    public function model($type)
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
    public function readObject($object, $key)
    {
        return $this->service('ReadObject')->read($object, $key);
    }

    /**
     * Run a report.
     *
     * @param Model|int $report
     * @return ReportBuilder
     */
    public function report($report): ReportBuilder
    {
        if (!$report instanceof Model) {
            $report = $this->model('Report')->readOrFail($report);
        }

        return new ReportBuilder($this->service('ReportService'), $report);
    }

    /**
     * Invoke an action.
     *
     * @param string action
     * @param object|array $object
     * @return \stdClass
     */
    public function invokeAction($action, $object)
    {
        return $this->service('InvokeAction')->invokeAction($action, $object);
    }

    /**
     * Get an instance of the specified service.
     *
     * @param string $name
     * @return mixed
     */
    public function service($name)
    {
        return $this->services[$name] ?? $this->services[$name] = $this->makeService($name);
    }

    /**
     * Wrap the specified closure in a transaction.
     *
     * @param Closure $callback
     */
    public function transaction(Closure $callback)
    {
        $this->service('TransactionService')->transaction($callback);
    }

    /**
     * Start a transaction.
     *
     * @param int $timeout
     */
    public function startTransaction(int $timeout = 60)
    {
        $this->service('TransactionService')->startTransaction($timeout);
    }

    /**
     * Rollback the transaction.
     */
    public function rollbackTransaction()
    {
        $this->service('TransactionService')->rollback();
    }

    /**
     * Commit the transaction.
     */
    public function commitTransaction()
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
    public function updateObject($object, $attributes)
    {
        return $this->service('UpdateObject')->update($object, $attributes);
    }

    /**
     * Determine the version of Pace running on the server.
     *
     * @return array
     */
    public function version()
    {
        return $this->service('Version')->get();
    }

    /**
     * Assemble the specified service's WSDL.
     *
     * @param $service
     * @return string
     */
    protected function getServiceWsdl($service)
    {
        return $this->url . $service . '?wsdl';
    }

    /**
     * Create a new instance of the specified service.
     *
     * @param string $service
     * @return mixed
     */
    protected function makeService($service)
    {
        $class = 'Pace\\Services\\' . $service;

        if (!class_exists($class)) {
            throw new InvalidArgumentException('Service [$service] is not implemented');
        }

        $soap = $this->soapFactory->make($this->getServiceWsdl($service));

        return new $class($soap);
    }
}
