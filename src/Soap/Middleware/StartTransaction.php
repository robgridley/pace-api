<?php

namespace Pace\Soap\Middleware;

use SoapHeader;

class StartTransaction
{
    /**
     * Add the start transaction SOAP header.
     *
     * @param array $headers
     * @return array
     */
    public function __invoke(array $headers): array
    {
        $headers[] = new SoapHeader('transaction', 'process', 'startTransaction');

        return $headers;
    }
}
