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
    public function find(string $object, string $filter): array
    {
        $request = ['in0' => $object, 'in1' => $filter];

        $response = $this->soap->find($request);

        return isset($response->out->string) ? (array)$response->out->string : [];
    }

    /**
     * Find and sort objects.
     *
     * @param string $object
     * @param string $filter
     * @param array $sort
     * @return array
     */
    public function findAndSort(string $object, string $filter, array $sort): array
    {
        $request = ['in0' => $object, 'in1' => $filter, 'in2' => $sort];

        $response = $this->soap->findAndSort($request);

        return isset($response->out->string) ? (array)$response->out->string : [];
    }

    /**
     * Find, sort and limit objects.
     *
     * @param string $object
     * @param string $filter
     * @param array|null $sort
     * @param int $offset
     * @param int $limit
     * @return array
     */
    public function findSortAndLimit(string $object, string $filter, ?array $sort, int $offset, int $limit): array
    {
        $request = ['in0' => $object, 'in1' => $filter, 'in2' => $sort, 'in3' => $offset, 'in4' => $limit];

        $response = $this->soap->findSortAndLimit($request);

        return isset($response->out->string) ? (array)$response->out->string : [];
    }

    /**
     * Call the find object aggregate service.
     *
     * @param string $object
     * @param string $filter
     * @param array|null $sort
     * @param int $offset
     * @param int $limit
     * @param array $fields
     * @param mixed $primaryKey
     * @return array
     */
    public function loadValueObjects(string $object, string $filter, ?array $sort, int $offset, int $limit, array $fields, mixed $primaryKey = null): array
    {
        $request = [
            'in0' => [
                'objectName' => $object,
                'xpathFilter' => $filter,
                'xpathSorts' => $sort,
                'offset' => $offset,
                'limit' => $limit,
                'fields' => $fields,
                'primaryKey' => $primaryKey,
            ],
        ];

        $response = $this->soap->loadValueObjects($request);

        if (is_array($response->out->valueObjects->ValueObject)) {
            return $response->out->valueObjects->ValueObject;
        }

        return [$response->out->valueObjects->ValueObject];
    }
}
