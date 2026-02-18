<?php

namespace Pace;

use SoapClient;

abstract class Service
{
    /**
     * Create a new service instance.
     *
     * @param SoapClient $soap
     */
    public function __construct(protected SoapClient $soap)
    {
    }
}
