<?php

namespace Pace\Services;

use Pace\Service;

class UpdateObject extends Service
{
    /**
     * Update an object.
     *
     * @param string $object
     * @param array $attributes
     * @return array
     */
    public function update(string $object, array $attributes): array
    {
        $request = [lcfirst($object) => $attributes];

        $response = $this->soap->{'update' . $object}($request);

        return (array)$response->out;
    }
}
