<?php

namespace Pace\Soap\Middleware;

use SoapHeader;

class CommitTransaction
{
    /**
     * Add the commit transaction SOAP header.
     *
     * @param array $headers
     * @return array
     */
    public function __invoke(array $headers): array
    {
        $headers[] = new SoapHeader('transaction', 'process', 'commit');

        return $headers;
    }
}
