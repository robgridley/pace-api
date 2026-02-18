<?php

namespace Pace\Contracts\Soap;

use SoapClient;

interface Factory
{
    /**
     * Add a new SOAP to PHP type mapping.
     *
     * @param TypeMapping $mapping
     */
    public function addTypeMapping(TypeMapping $mapping): void;

    /**
     * Create a new SoapClient instance.
     *
     * @param string $wsdl
     * @return SoapClient
     */
    public function make(string $wsdl): SoapClient;

    /**
     * Set the specified SOAP client option.
     *
     * @param string $key
     * @param mixed $value
     */
    public function setOption(string $key, mixed $value): void;

    /**
     * Bulk set the specified SOAP client options.
     *
     * @param array $options
     */
    public function setOptions(array $options): void;
}
