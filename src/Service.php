<?php

namespace Pace;

use SoapClient;

abstract class Service
{
    /**
     * The SOAP client instance.
     *
     * @var SoapClient
     */
    protected $soap;

    /**
     * Create a new service instance.
     *
     * @param SoapClient $soap
     */
    public function __construct(SoapClient $soap)
    {
        $this->soap = $soap;
    }
}
