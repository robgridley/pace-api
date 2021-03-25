<?php

namespace Pace\Services;

use Pace\Service;

class DeleteObject extends Service
{
    /**
     * Delete an object by its primary key.
     *
     * @param string $object
     * @param int|string $key
     */
    public function delete($object, $key)
    {
        $request = ['in0' => $object, 'in1' => $key];

        $this->soap->deleteObject($request);
    }
}
