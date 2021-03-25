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
    public function clone($object, array $attributes, array $newAttributes, $newKey = null, array $newParent = null)
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
