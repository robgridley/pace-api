<?php

namespace Pace\Services;

use Pace\Service;

class InvokeAction extends Service
{
    /**
     * Invoke the specified action.
     *
     * @param string $action
     * @param mixed ...$parameters
     * @return array
     */
    public function invokeAction(string $action, mixed ...$parameters): array
    {
        return (array)$this->soap->{$action}($parameters)->out;
    }
}
