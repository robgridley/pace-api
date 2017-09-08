<?php

namespace Pace\Services;

use Pace\Service;

class FindObjects extends Service
{
    /**
     * Find objects.
     *
     * @param string $object
     * @param string $filter
     * @return array
     */
    public function find($object, $filter)
    {
        $request = ['in0' => $object, 'in1' => $filter];

        $response = $this->soap->find($request);

        return isset($response->out->string) ? $response->out->string : [];
    }

    /**
     * Find and sort objects.
     *
     * @param string $object
     * @param string $filter
     * @param array $sort
     * @return array
     */
    public function findAndSort($object, $filter, array $sort)
    {
        $request = ['in0' => $object, 'in1' => $filter, 'in2' => $sort];

        $response = $this->soap->findAndSort($request);

        return isset($response->out->string) ? $response->out->string : [];
    }
}
