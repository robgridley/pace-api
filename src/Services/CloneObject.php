<?php

namespace Pace\Services;

use Pace\Service;

class CloneObject extends Service
{
    /**
     * Clone an object.
     *
     * @param string $object
     * @param array $attributes
     * @param array $newAttributes
     * @param int|string|null $newKey
     * @param array|null $newParent
     * @return array
     */
    public function clone(string $object, array $attributes, array $newAttributes, mixed $newKey = null, array $newParent = null): array
    {
        $request = [
            $object => $attributes,
            $object . 'AttributesToOverride' => $newAttributes,
            'newPrimaryKey' => $newKey,
            'newParent' => $newParent,
        ];

        $response = $this->soap->{'clone' . $object}($request);

        return (array)$response->out;
    }
}
