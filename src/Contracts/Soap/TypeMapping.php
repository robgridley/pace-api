<?php

namespace Pace\Contracts\Soap;

interface TypeMapping
{
    /**
     * Get the name of the SOAP data type.
     *
     * @return string
     */
    public function getTypeName(): string;

    /**
     * Get the type namespace.
     *
     * @return string
     */
    public function getTypeNamespace(): string;

    /**
     * Convert an XML string to a native PHP type.
     *
     * @param string $xml
     * @return mixed
     */
    public function fromXml(string $xml): mixed;

    /**
     * Convert a native PHP type to an XML string.
     *
     * @param mixed $php
     * @return string
     */
    public function toXml(mixed $php): string;
}
