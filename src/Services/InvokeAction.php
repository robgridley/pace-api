<?php

namespace Pace\Services;

use Pace\Service;

class InvokeAction extends Service
{
    /**
     * Invoke action.
     *
     * @param string $action
     * @param array $attributes
     * @return array
     */
    public function invokeAction($action, $key)
    {
	$request = ['in0' => $key];

        $response = $this->soap->{$action}($request);

        return (array)$response->out;
    }
}
