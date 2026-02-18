<?php

namespace Pace\InvokeAction;

use ArrayAccess;
use BadMethodCallException;
use JsonSerializable;
use Pace\Client;
use Pace\Model;

class InvokeActionResponse implements ArrayAccess, JsonSerializable
{
    /**
     * Create a new invoke action response instance.
     *
     * @param Client $client
     * @param array $response
     */
    public function __construct(protected Client $client, protected array $response)
    {
    }

    /**
     * Convert the instance to an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->response;
    }

    /**
     * Convert the instance to a model.
     *
     * @param string $type
     * @return Model
     */
    public function toModel(string $type): Model
    {
        $model = $this->client->model($type)->newInstance($this->response);
        $model->exists = true;

        return $model;
    }

    /**
     * Determine whether the offset exists.
     *
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->response);
    }

    /**
     * Retrieve the offset.
     *
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->response[$offset];
    }

    /**
     * Set the offset.
     *
     * @param mixed $offset
     * @param mixed $value
     * @return void
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new BadMethodCallException('The response is read-only.');
    }

    /**
     * Unset the offset.
     *
     * @param mixed $offset
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
        throw new BadMethodCallException('The response is read-only.');
    }

    /**
     * Convert the instance to JSON serializable data.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->response;
    }
}
