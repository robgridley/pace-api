<?php

namespace Pace;

use Exception;
use ArrayAccess;
use JsonSerializable;
use Pace\XPath\Builder;

class Model implements ArrayAccess, JsonSerializable
{
    /**
     * Camel-cased object type.
     *
     * @var string
     */
    protected $type;

    /**
     * The web service client instance.
     *
     * @var Client
     */
    protected $client;

    /**
     * The object containing the model's current properties.
     *
     * @var object
     */
    protected $object;

    /**
     * The object containing the model's original properties.
     *
     * @var object
     */
    protected $original;

    /**
     * Indicates if this model exists in Pace.
     *
     * @var bool
     */
    public $exists = false;

    /**
     * Create a new model instance.
     *
     * @param Client $client
     * @param string $type
     * @param object $object
     */
    public function __construct(Client $client, $type, $object = null)
    {
        $this->client = $client;
        $this->type = $type;

        $this->object = is_null($object) ? new \stdClass() : $object;

        $this->syncOriginal();
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
     * Get the specified model property.
     *
     * @param string $property
     * @return mixed
     */
    public function __get($property)
    {
        return $this->getProperty($property);
    }

    /**
     * Determine if the specified model property is set.
     *
     * @param string $property
     * @return bool
     */
    public function __isset($property)
    {
        return isset($this->object->$property);
    }

    /**
     * Set the specified model property to the supplied value.
     *
     * @param string $property
     * @param mixed $value
     */
    public function __set($property, $value)
    {
        $this->object->$property = $value;
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
     * Destroy the specified model property.
     *
     * @param string $property
     */
    public function __unset($property)
    {
        unset($this->object->$property);
    }

    /**
     * Delete the model from the web service.
     *
     * @param string $primaryKey
     * @return bool|null
     * @throws Exception if the primary key cannot be read
     */
    public function delete($primaryKey = 'id')
    {
        if (is_null($this->getProperty($primaryKey))) {
            throw new Exception('Could not read the primary key.');
        }

        if ($this->exists) {
            $this->client->deleteObject($this->getType(), $this->getProperty($primaryKey));
            $this->exists = false;

            return true;
        }
    }

    /**
     * Persist the model in the web service as a duplicate and restore the model's properties.
     *
     * @param int|string $newPrimaryKey
     * @return Model|null
     */
    public function duplicate($newPrimaryKey = null)
    {
        if ($this->exists) {
            $response = $this->client->cloneObject($this->getType(), $this->object, $this->getDirty(), $newPrimaryKey);

            $instance = $this->newInstance($response);
            $instance->exists = true;

            $this->restore();

            return $instance;
        }
    }

    /**
     * Find primary keys in the web service using a filter (and optionally sort).
     *
     * @param string $filter
     * @param array $sort
     * @return KeyCollection|null
     */
    public function find($filter = null, $sort = null)
    {
        $keys = $this->client->findObjects($this->getType(), $filter, $sort);

        return $this->newKeyCollection((array)$keys);
    }

    /**
     * Get the object properties which have changed since the last sync.
     *
     * @return object
     */
    public function getDirty()
    {
        return (object)array_diff_assoc((array)$this->object, (array)$this->original);
    }

    /**
     * Get the type of the model.
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Determine if the model has changed since the last sync.
     *
     * @return bool
     */
    public function isDirty()
    {
        return $this->original !== $this->object;
    }

    /**
     * Convert the model to a serializable array.
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return (array)$this->object;
    }

    /**
     * Create a new model instance.
     *
     * @param object $object
     * @return Model
     */
    public function newInstance($object = null)
    {
        return new static($this->client, $this->getType(), $object);
    }

    /**
     * Determine is the specified model property exists.
     *
     * @param string $property
     * @return bool
     */
    public function offsetExists($property)
    {
        return property_exists($this->object, $property);
    }

    /**
     * Get the specified model property.
     *
     * @param string $property
     * @return mixed
     */
    public function offsetGet($property)
    {
        return $this->getProperty($property);
    }

    /**
     * Set the specified model property to the supplied value.
     *
     * @param string $property
     * @param mixed $value
     */
    public function offsetSet($property, $value)
    {
        $this->object->$property = $value;
    }

    /**
     * Destroy the specified model property.
     *
     * @param string $property
     */
    public function offsetUnset($property)
    {
        unset($this->object->$property);
    }

    /**
     * Read a new model from the web service using the specified primary key.
     *
     * @param int|string $key
     * @return Model
     */
    public function read($key)
    {
        // This is intentionally not strict. The API considers an
        // integer 0 to be null and will respond with a fault.
        if ($key == null) {
            return null;
        }

        $response = $this->client->readObject($this->getType(), $key);

        if (empty($response)) {
            return null;
        }

        $instance = $this->newInstance($response);
        $instance->exists = true;

        return $instance;
    }

    /**
     * Read a related model from the web service using the supplied property's value.
     *
     * @param string $property
     * @param string $model
     * @return Model
     */
    public function related($property, $model = null)
    {
        if (!is_null($this->getProperty($property))) {
            return $this->client->{$model ?: $property}->read($this->getProperty($property));
        }
    }

    /**
     * Persist the model in the web service.
     *
     * @return Model
     */
    public function save()
    {
        // If the object already exists in Pace, then update it.
        if ($this->exists) {
            $this->object = $this->client->updateObject($this->getType(), $this->object);

        // Otherwise create it in Pace and fill in any default values.
        } else {
            $this->object = $this->client->createObject($this->getType(), $this->object);
            $this->exists = true;
        }

        $this->syncOriginal();

        return $this;
    }

    /**
     * Get the specified property or null if it does not exist.
     *
     * @param string $property
     * @return mixed
     */
    protected function getProperty($property)
    {
        return property_exists($this->object, $property) ? $this->object->$property : null;
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

    /**
     * Restore the current model properties from the original.
     */
    protected function restore()
    {
        $this->object = clone $this->original;
    }

    /**
     * Sync the original object properties with the current.
     */
    protected function syncOriginal()
    {
        $this->original = clone $this->object;
    }
}
