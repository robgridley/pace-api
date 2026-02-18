<?php

namespace Pace\Services;

use Pace\Client;
use Pace\Service;
use SoapFault;

class ReadObject extends Service
{
    /**
     * Read an object by its primary key.
     *
     * @param string $object
     * @param int|string $key
     * @return array|null
     * @throws SoapFault if an unexpected SOAP error occurs.
     */
    public function read(string $object, mixed $key): ?array
    {
        $request = [lcfirst($object) => [Client::PRIMARY_KEY => $key]];

        try {
            $response = $this->soap->{'read' . $object}($request);
            return (array)$response->out;

        } catch (SoapFault $exception) {
            if ($this->isObjectNotFound($exception)) {
                return null;
            }

            throw $exception;
        }
    }

    /**
     * Determine if the SOAP fault is for a non-existent object.
     *
     * @param SoapFault $exception
     * @return bool
     */
    protected function isObjectNotFound(SoapFault $exception): bool
    {
        return strpos($exception->getMessage(), 'Unable to locate object') === 0;
    }
}
