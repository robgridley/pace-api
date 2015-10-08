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
    const DUPLICATE_SERVICE = 'CloneObject';
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
     * Object model names which are not pretty.
     *
     * @var array
     */
    protected $irregularNames = [
        'csr' => ['cSR', 'CSR'],
        'glLocation' => ['gLLocation', 'GLLocation'],
        'uomType' => ['uOMType', 'UOMType'],
        'crmStatus' => ['cRMStatus', 'CRMStatus'],
        'glSchedule' => ['gLSchedule', 'GLSchedule'],
        'glAccountBalanceSummary' => ['gLAccountBalanceSummary', 'GLAccountBalanceSummary'],
        'glJournalEntryAudit' => ['gLJournalEntryAudit', 'GLJournalEntryAudit'],
        'rssChannel' => ['rSSChannel', 'RSSChannel'],
        'faSetup' => ['fASetup', 'FASetup'],
        'arSetup' => ['aRSetup', 'ARSetup'],
        'glScheduleLine' => ['gLScheduleLine', 'GLScheduleLine'],
        'uom' => ['uOM', 'UOM'],
        'jmfReceivedMessageTransaction' => ['jMFReceivedMessageTransaction', 'JMFReceivedMessageTransaction'],
        'uomRange' => ['uOMRange', 'UOMRange'],
        // ...
    ];

    /**
     * Cached models.
     *
     * @var array
     */
    protected $models = [];

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
     * Dynamically get the specified object model.
     *
     * @param string $name
     * @return Model
     */
    public function __get($name)
    {
        if (!isset($this->models[$name])) {
            $this->models[$name] = new Model($this, $name);
        }

        return $this->models[$name];
    }

    /**
     * Get the camel-cased object model name.
     *
     * @param string $name
     * @return string
     */
    public function camelCase($name)
    {
        if (array_key_exists($name, $this->irregularNames)) {
            return $this->irregularNames[$name][0];
        }

        return $name;
    }

    /**
     * Find the primary keys for the specified object using a filter and optionally sort.
     *
     * @param string $name
     * @param string $filter
     * @param array $sort
     * @return array|null
     */
    public function findObjects($name, $filter, array $sort = null)
    {
        $name = $this->studlyCase($name);

        if (!empty($sort)) {
            $response = $this->soapClient(Client::FIND_SERVICE)
                ->findAndSort(['in0' => $name, 'in1' => $filter, 'in2' => $sort])
                ->out;
        } else {
            $response = $this->soapClient(Client::FIND_SERVICE)
                ->find(['in0' => $name, 'in1' => $filter])
                ->out;
        }

        return isset($response->string) ? (array)$response->string : null;
    }

    /**
     * Read the specified object by its primary key.
     *
     * @param string $name
     * @param mixed $key
     * @return \stdClass|null
     * @throws SoapFault if an unexpected SOAP error occurs.
     */
    public function readObject($name, $key)
    {
        $method = 'read' . $this->studlyCase($name);

        try {
            return $this->soapClient(Client::READ_SERVICE)
                ->$method([$this->camelCase($name) => [self::PRIMARY_KEY => $key]])
                ->out;
        } catch(SoapFault $e) {
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
     * Get the studly-cased object model name.
     *
     * @param string $name
     * @return string
     */
    public function studlyCase($name)
    {
        if (array_key_exists($name, $this->irregularNames)) {
            return $this->irregularNames[$name][1];
        }

        return ucfirst($name);
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
