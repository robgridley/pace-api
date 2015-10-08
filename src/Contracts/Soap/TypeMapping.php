<?php

namespace Pace\Contracts\Soap;

interface TypeMapping
{
    /**
     * Get the name of the SOAP data type.
     *
     * @return string
     */
    public function getTypeName();

    /**
     * Get the type namespace.
     *
     * @return string
     */
    public function getTypeNamespace();

    /**
     * Convert an XML string to a native PHP type.
     *
     * @param string $xml
     * @return mixed
     */
    public function fromXml($xml);

    /**
     * Convert a native PHP type to an XML string.
     *
     * @param mixed $php
     * @return string
     */
    public function toXml($php);
}
