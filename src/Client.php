<?php

namespace Pace;

use SoapFault;
use Pace\Contracts\Soap\Factory as SoapFactory;

class Client
{
    /**
     * The object context services.
     */
    const CREATE_SERVICE = 'CreateObject';
    const DELETE_SERVICE = 'DeleteObject';
    const CLONE_SERVICE = 'CloneObject';
    const FIND_SERVICE = 'FindObjects';
    const READ_SERVICE = 'ReadObject';
    const UPDATE_SERVICE = 'UpdateObject';

    /**
     * The system services.
     */
    const ATTACHMENT_SERVICE = 'AttachmentService';
    const FIND_COMPANY_SERVICE = 'FindCompany';
    const GEOLOCATE_SERVICE = 'GeoLocate';
    const INSPECT_SERVICE = 'SystemInspector';
    const INVOKE_ACTION_SERVICE = 'InvokeAction';
    const INVOKE_CONNECT_SERVICE = 'InvokePaceConnect';
    const INVOKE_PROCESS_SERVICE = 'InvokeProcess';
    const VERSION_SERVICE = 'Version';

    /**
     * The primary key field.
     */
    const PRIMARY_KEY = 'primaryKey';

    /**
     * Cached services (SOAP clients).
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
        $this->soapFactory = $soapFactory;

        $this->url = sprintf('%s://%s/rpc/services/', $scheme, $host);
    }

    /**
     * Dynamically retrieve the specified model.
     *
     * @param string $name
     * @return Model
     */
    public function __get($name)
    {
        return $this->model(Type::fromPropertyName($name));
    }

    /**
     * Clone an object.
     *
     * @param Type $type
     * @param object|array $object
     * @param object|array $newObject
     * @param string|int $newPrimaryKey
     * @param object|array $newParent
     * @return \stdClass
     */
    public function cloneObject(Type $type, $object, $newObject, $newPrimaryKey = null, $newParent = null)
    {
        $method = 'clone' . $type;

        return $this->soapClient(Client::CLONE_SERVICE)->$method([
            $type->name() => $object,
            $type . 'AttributesToOverride' => $newObject,
            'newPrimaryKey' => $newPrimaryKey,
            'newParent' => $newParent
        ])->out;
    }

    /**
     * Create an object.
     *
     * @param Type $type
     * @param object|array $object
     * @return \stdClass
     */
    public function createObject(Type $type, $object)
    {
        $method = 'create' . $type;

        return $this->soapClient(Client::CREATE_SERVICE)
            ->$method([$type->camelCaseName() => $object])
            ->out;
    }

    /**
     * Delete an object by its primary key.
     *
     * @param Type $type
     * @param int|string $key
     */
    public function deleteObject(Type $type, $key)
    {
        return $this->soapClient(Client::DELETE_SERVICE)
            ->deleteObject(['in0' => $type->name(), 'in1' => $key]);
    }

    /**
     * Find primary keys for the specified object using a filter (and optionally sort).
     *
     * @param Type $type
     * @param string $filter
     * @param array $sort
     * @return mixed
     */
    public function findObjects(Type $type, $filter, array $sort = null)
    {
        if (!empty($sort)) {
            $response = $this->soapClient(Client::FIND_SERVICE)
                ->findAndSort(['in0' => $type->name(), 'in1' => $filter, 'in2' => $sort])
                ->out;
        } else {
            $response = $this->soapClient(Client::FIND_SERVICE)
                ->find(['in0' => $type->name(), 'in1' => $filter])
                ->out;
        }

        return isset($response->string) ? $response->string : null;
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
     * Read the specified object type by its primary key.
     *
     * @param Type $type
     * @param mixed $key
     * @return \stdClass|null
     * @throws SoapFault if an unexpected SOAP error occurs.
     */
    public function readObject(Type $type, $key)
    {
        $method = 'read' . $type;

        try {
            return $this->soapClient(Client::READ_SERVICE)
                ->$method([$type->camelCaseName() => [self::PRIMARY_KEY => $key]])
                ->out;
        } catch (SoapFault $e) {
            if (strpos($e->getMessage(), 'Unable to locate object') !== 0) {
                throw $e;
            }
        }
    }

    /**
     * Get a SOAP client for the specified service.
     *
     * @param $service
     * @return \SoapClient
     */
    public function soapClient($service)
    {
        if (!isset($this->services[$service])) {
            $this->services[$service] = $this->soapFactory->make($this->getServiceWsdl($service));
        }

        return $this->services[$service];
    }

    /**
     * Update an object.
     *
     * @param Type $type
     * @param \stdClass|array $object
     * @return mixed
     */
    public function updateObject(Type $type, $object)
    {
        $method = 'update' . $type;

        return $this->soapClient(Client::UPDATE_SERVICE)
            ->$method([$type->camelCaseName() => $object])
            ->out;
    }

    /**
     * Check the version of Pace running on the server.
     *
     * @return string
     */
    public function version()
    {
        return $this->soapClient(self::VERSION_SERVICE)->getVersion()->out;
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
}
