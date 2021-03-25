<?php

namespace Pace\Soap\Middleware;

use SoapHeader;

class Transaction
{
    /**
     * The transaction ID.
     *
     * @var string
     */
    protected $id;

    /**
     * Create a new middleware instance.
     *
     * @param string $id
     */
    public function __construct(string $id)
    {
        $this->id = $id;
    }

    /**
     * Add the transaction ID SOAP header.
     *
     * @param array $headers
     * @return array
     */
    public function __invoke(array $headers): array
    {
        $headers[] = new SoapHeader('transaction', 'txnId', $this->id);

        return $headers;
    }
}
