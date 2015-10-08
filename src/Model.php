<?php

namespace Pace;

use ArrayAccess;
use JsonSerializable;
use Pace\XPath\Builder;

class Model implements ArrayAccess, JsonSerializable
{
    /**
     * The client instance.
     *
     * @var Client
     */
    protected $client;

    /**
     * Camel-cased model name.
     *
     * @var string
     */
    protected $modelName;

    /**
     * The SOAP response object.
     *
     * @var \stdClass
     */
    protected $response;

    /**
     * Create a new model instance.
     *
     * @param Client $client
     * @param string $modelName
     * @param \stdClass $response
     */
    public function __construct(Client $client, $modelName, \stdClass $response = null)
    {
        $this->client = $client;
        $this->modelName = $modelName;
        $this->response = $response;
    }

    /**
     * Magically handle method calls.
     *
     * @param string $method
     * @param array $arguments
     * @return mixed
     */
    public function __call($method, array $arguments)
    {
        if (count($arguments) == 0) {
            return $this->related($method);
        }

        return $this->newBuilder()->$method(...$arguments);
    }

    /**
     * Get the specified response property.
     *
     * @param string $property
     * @return mixed
     */
    public function __get($property)
    {
        return $this->response->$property;
    }

    /**
     * Determine if the specified response property is set.
     *
     * @param $property
     * @return bool
     */
    public function __isset($property)
    {
        return isset($this->response->$property);
    }

    /**
     * Set the specified response property to the supplied value.
     *
     * @param $property
     * @param $value
     */
    public function __set($property, $value)
    {
        $this->response->$property = $value;
    }

    /**
     * Get a string representation of the instance.
     *
     * @return string
     */
    public function __toString()
    {
        return json_encode($this);
    }

    /**
     * Destroy the specified response property.
     *
     * @param $property
     */
    public function __unset($property)
    {
        unset($this->response->$property);
    }

    /**
     * Find the primary keys using a filter and optionally sort.
     *
     * @param string $filter
     * @param array $sort
     * @return KeyCollection|null
     */
    public function find($filter = null, $sort = null)
    {
        $keys = $this->client->findObjects($this->getName(), $filter, $sort);

        return $this->newKeyCollection((array)$keys);
    }

    /**
     * Create a new model instance.
     *
     * @param \stdClass $response
     * @return Model
     */
    public function fresh(\stdClass $response = null)
    {
        return new static($this->client, $this->modelName, $response);
    }

    /**
     * Get the name of the model.
     *
     * @return string
     */
    public function getName()
    {
        return $this->modelName;
    }

    /**
     * Get the SOAP response object.
     *
     * @return \stdClass
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Convert this instance to a serializable array.
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return (array)$this->response;
    }

    /**
     * Determine is the specified response property exists.
     *
     * @param mixed $property
     * @return bool
     */
    public function offsetExists($property)
    {
        return property_exists($this->response, $property);
    }

    /**
     * Get the specified response property.
     *
     * @param mixed $property
     * @return mixed
     */
    public function offsetGet($property)
    {
        return $this->response->$property;
    }

    /**
     * Set the specified response property to the supplied value.
     *
     * @param mixed $property
     * @param mixed $value
     */
    public function offsetSet($property, $value)
    {
        $this->response->$property = $value;
    }

    /**
     * Destroy the specified response property.
     *
     * @param mixed $property
     */
    public function offsetUnset($property)
    {
        unset($this->response->$property);
    }

    /**
     * Read using the specified primary key.
     *
     * @param $key
     * @return Model
     */
    public function read($key)
    {
        $response = $this->client->readObject($this->getName(), $key);

        return empty($response) ? null : $this->fresh($response);
    }

    /**
     * Read a related model using the supplied property's value.
     *
     * @param string $property
     * @param string $modelName
     * @return Model
     */
    public function related($property, $modelName = null)
    {
        if (isset($this->response->$property)) {
            return $this->client->{$modelName ?: $property}->read($this->response->$property);
        }
    }

    /**
     * Create a new XPath builder.
     *
     * @return Builder
     */
    protected function newBuilder()
    {
        return new Builder($this);
    }

    /**
     * Create a new key collection with the supplied keys.
     *
     * @param array $keys
     * @return KeyCollection
     */
    protected function newKeyCollection(array $keys)
    {
        return new KeyCollection($this, $keys);
    }
}
