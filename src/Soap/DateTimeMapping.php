<?php

namespace Pace\Soap;

use Carbon\Carbon;
use InvalidArgumentException;
use Pace\Contracts\Soap\TypeMapping;
use SimpleXMLElement;

class DateTimeMapping implements TypeMapping
{
    /**
     * The format used by the web service.
     *
     * @var string
     */
    protected string $xmlFormat = 'Y-m-d\TH:i:s.u\Z';

    /**
     * Get the name of the SOAP data type.
     *
     * @return string
     */
    public function getTypeName(): string
    {
        return 'dateTime';
    }

    /**
     * Get the type namespace.
     *
     * @return string
     */
    public function getTypeNamespace(): string
    {
        return 'http://www.w3.org/2001/XMLSchema';
    }

    /**
     * Convert the supplied XML string to a Carbon instance.
     *
     * @param string $xml
     * @return Carbon
     */
    public function fromXml(string $xml): Carbon
    {
        return Carbon::createFromFormat($this->xmlFormat, new SimpleXMLElement($xml), 'UTC')
            ->timezone(date_default_timezone_get());
    }

    /**
     * Convert the supplied Carbon instance to an XML string.
     *
     * @param Carbon $php
     * @return string
     */
    public function toXml(mixed $php): string
    {
        if (!$php instanceof Carbon) {
            throw new InvalidArgumentException('PHP value must be a Carbon instance');
        }

        return sprintf(
            '<%1$s>%2$s</%1$s>',
            $this->getTypeName(),
            $php->copy()->timezone('UTC')->format($this->xmlFormat)
        );
    }
}
