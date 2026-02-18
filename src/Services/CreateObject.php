<?php

namespace Pace\Services;

use Pace\Service;

class CreateObject extends Service
{
    /**
     * Create an object.
     *
     * @param string $object
     * @param array $attributes
     * @return array
     */
    public function create(string $object, array $attributes): array
    {
        $request = [lcfirst($object) => $attributes];

        $response = $this->soap->{'create' . $object}($request);

        return (array)$response->out;
    }
}
