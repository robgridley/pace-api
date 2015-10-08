<?php

namespace Pace\Contracts\Soap;

interface Factory
{
    /**
     * Add a new SOAP to PHP type mapping.
     *
     * @param TypeMapping $mapping
     */
    public function addTypeMapping(TypeMapping $mapping);

    /**
     * Create a new SoapClient instance.
     *
     * @param string $wsdl
     * @return \SoapClient
     */
    public function make($wsdl);

    /**
     * Set the specified SOAP client option.
     *
     * @param string $key
     * @param mixed $value
     */
    public function setOption($key, $value);

    /**
     * Bulk set the specified SOAP client options.
     *
     * @param array $options
     */
    public function setOptions(array $options);
}
