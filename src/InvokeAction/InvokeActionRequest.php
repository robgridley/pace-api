<?php

namespace Pace\InvokeAction;

use Pace\Client;
use Pace\Model;
use Pace\Services\InvokeAction;

class InvokeActionRequest
{
    /**
     * Create a new invoke action request instance.
     *
     * @param Client $client
     * @param InvokeAction $service
     */
    public function __construct(protected Client $client, protected InvokeAction $service)
    {
    }

    /**
     * Magic method to invoke an action.
     *
     * @param string $action
     * @param array $arguments
     * @return InvokeActionResponse
     */
    public function __call(string $action, array $arguments): InvokeActionResponse
    {
        $parameters = [];

        foreach ($arguments as $key => $value) {
            if (is_int($key)) {
                $key = "in$key";
            }
            if ($value instanceof Model) {
                $value = [
                    'primaryKey' => $value->key(),
                ];
            } elseif (is_array($value)) {
                array_walk_recursive($value, function (&$value) {
                    if ($value instanceof Model) {
                        $value = $value->key();
                    }
                });
            }
            $parameters[$key] = $value;
        }

        $response = $this->service->invokeAction($action, ...$parameters);

        return new InvokeActionResponse($this->client, $response);
    }
}
