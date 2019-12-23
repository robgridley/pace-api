<?php

namespace Pace\Soap\Middleware;

use SoapHeader;

class RollbackTransaction
{
    /**
     * Add the rollback transaction SOAP header.
     *
     * @param array $headers
     * @return array
     */
    public function __invoke(array $headers): array
    {
        $headers[] = new SoapHeader('transaction', 'process', 'rollback');

        return $headers;
    }
}
