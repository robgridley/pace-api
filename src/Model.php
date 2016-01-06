<?php

namespace Pace;

use ArrayAccess;
use JsonSerializable;
use Pace\XPath\Builder;
use UnexpectedValueException;
use Doctrine\Common\Inflector\Inflector;

class Model implements ArrayAccess, JsonSerializable
{
    /**
     * The object type.
     *
     * @var Type
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
     * Auto-magically loaded "belongs to" relationships.
     *
     * @var array
     */
    protected $relations = [];

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
     * @param Type|string $type
     * @param object $object
     */
    public function __construct(Client $client, $type, $object = null)
    {
        $this->client = $client;
        $this->type = $type instanceof Type ? $type : new Type($type);
        $this->object = is_object($object) ? $object : new \stdClass();

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
        if ($this->isBuilderMethod($method)) {
            return call_user_func_array([$this->newBuilder(), $method], $arguments);
        }

        return $this->getRelatedFromMethod($method);
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
        return $this->getProperty($property) !== null;
    }

    /**
     * Dynamically set the specified model property to the supplied value.
     *
     * @param string $property
     * @param mixed $value
     */
    public function __set($property, $value)
    {
        $this->setProperty($property, $value);
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
     * Fetch a "belongs to" relationship.
     *
     * @param string $relatedType
     * @param string $foreignKey
     * @return Model|null
     */
    public function belongsTo($relatedType, $foreignKey)
    {
        if ($this->isCompoundKey($foreignKey)) {
            $key = $this->getCompoundKey($foreignKey);
        } else {
            $key = $this->getProperty($foreignKey);
        }

        return $this->client->model($relatedType)->read($key);
    }

    /**
     * Delete the model from the web service.
     *
     * @param string $primaryKey
     * @return bool|null
     */
    public function delete($primaryKey = null)
    {
        if ($this->exists) {
            $this->client->deleteObject($this->getType(), $this->key($primaryKey));
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
     * Refresh the properties of the model from the web service.
     *
     * @param string $primaryKey
     * @return Model|null
     */
    public function fresh($primaryKey = null)
    {
        if (!$this->exists) {
            return null;
        }

        $this->read($this->key($primaryKey));

        return $this;
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
     * @return Type
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Fetch a "has many" relationship.
     *
     * @param string $relatedType
     * @param string $foreignKey
     * @param string $primaryKey
     * @return Builder
     */
    public function hasMany($relatedType, $foreignKey, $primaryKey = null)
    {
        $builder = $this->client->model($relatedType)->newBuilder();

        if ($this->isCompoundKey($foreignKey)) {
            foreach ($this->getCompoundKeyArray($foreignKey, $primaryKey) as $attribute => $value) {
                $builder->filter('@' . $attribute, $value);
            }
        } else {
            $builder->filter('@' . $foreignKey, $this->key($primaryKey));
        }

        return $builder;
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
     * Join an array of keys into a compound key.
     *
     * @param array $keys
     * @return string
     */
    public function joinKeys(array $keys)
    {
        return implode(':', $keys);
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
     * Get the model's primary key.
     *
     * @param string $primaryKey
     * @return string|int
     * @throws UnexpectedValueException if the key is null.
     */
    public function key($primaryKey = null)
    {
        $key = $this->getProperty($primaryKey ?: $this->guessPrimaryKey());

        if ($key == null) {
            throw new UnexpectedValueException('Key must not be null.');
        }

        return $key;
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
        return $this->hasProperty($property);
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
        $this->setProperty($property, $value);
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
     * Read a new model from the web service using the specified primary key or throw an exception.
     *
     * @param int|string $key
     * @return Model
     * @throws ModelNotFoundException if the key does not exist.
     */
    public function readOrFail($key)
    {
        $result = $this->read($key);

        if (is_null($result)) {
            throw new ModelNotFoundException("$this->type [$key] does not exist.");
        }

        return $result;
    }

    /**
     * Persist the model in the web service.
     *
     * @return Model
     */
    public function save()
    {
        if ($this->exists) {
            // Update an existing object in Pace.
            $this->object = $this->client->updateObject($this->getType(), $this->object);

        } else {
            // Create a new object in Pace and fill default values.
            $this->object = $this->client->createObject($this->getType(), $this->object);
            $this->exists = true;
        }

        $this->syncOriginal();

        return $this;
    }

    /**
     * Split a compound key into an array.
     *
     * @param string $key
     * @return array
     */
    public function splitKey($key = null)
    {
        if (is_null($key)) {
            $key = $this->key();
        }

        return explode(':', $key);
    }

    /**
     * Get a compound key for a "belongs to" relationship.
     *
     * @param string $foreignKey
     * @return string
     */
    protected function getCompoundKey($foreignKey)
    {
        $keys = [];

        foreach ($this->splitKey($foreignKey) as $key) {
            $keys[] = $this->getProperty($key);
        }

        return $this->joinKeys($keys);
    }

    /**
     * Get a compound key array for a "has many" relationship.
     *
     * @param string $foreignKey
     * @param string $primaryKey
     * @return array
     */
    protected function getCompoundKeyArray($foreignKey, $primaryKey)
    {
        return array_combine(
            $this->splitKey($foreignKey),
            $this->splitKey($this->key($primaryKey))
        );
    }

    /**
     * Get the specified property or null if it does not exist.
     *
     * @param string $property
     * @return mixed
     */
    protected function getProperty($property)
    {
        // First, check if the field exists.
        if (property_exists($this->object, $property)) {
            return $this->object->$property;
        }

        // Next, check if a user defined field exists.
        if (property_exists($this->object, "U_$property")) {
            return $this->object->{"U_$property"};
        }
    }

    /**
     * Auto-magically fetch relationships.
     *
     * @param string $method
     * @return Builder|Model|null
     */
    protected function getRelatedFromMethod($method)
    {
        // If the called method name exists as a property on the model,
        // assume it is the camel-cased related type and the property
        // contains the foreign key for a "belongs to" relationship.
        if ($this->hasProperty($method)) {
            if (!$this->relationLoaded($method)) {
                $relatedType = Type::fromCamelCase($method);
                $this->relations[$method] = $this->belongsTo($relatedType, $method);
            }

            return $this->relations[$method];
        }

        // Otherwise, the called method name should be a pluralized,
        // camel-cased related type for a "has many" relationship.
        $type = Type::fromCamelCase(Inflector::singularize($method));
        return $this->hasMany($type, $this->getType()->camelize());
    }

    /**
     * Attempt to guess the primary key field.
     *
     * @return string
     */
    protected function guessPrimaryKey()
    {
        if ($this->hasProperty(Client::PRIMARY_KEY)) {
            return Client::PRIMARY_KEY;
        }

        if ($this->hasProperty('id')) {
            return 'id';
        }

        return $this->getType()->camelize();
    }

    /**
     * Determine if the specified property exists.
     *
     * @param string $property
     * @return bool
     */
    protected function hasProperty($property)
    {
        return property_exists($this->object, $property) || property_exists($this->object, "U_$property");
    }

    /**
     * Determine if a dynamic call should be passed to the XPath builder class.
     *
     * @param string $name
     * @return bool
     */
    protected function isBuilderMethod($name)
    {
        return method_exists(Builder::class, $name) && is_callable([Builder::class, $name]);
    }

    /**
     * Check if the specified key is a compound key.
     *
     * @param mixed $key
     * @return bool
     */
    protected function isCompoundKey($key)
    {
        return strpos($key, ':') !== false;
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
     * Determine if a "belongs to" relationship has already been loaded.
     *
     * @param string $relation
     * @return bool
     */
    protected function relationLoaded($relation)
    {
        return array_key_exists($relation, $this->relations);
    }

    /**
     * Restore the current model properties from the original.
     */
    protected function restore()
    {
        $this->object = clone $this->original;
    }

    /**
     * Set the specified model property to the supplied value.
     *
     * @param string $property
     * @param mixed $value
     */
    protected function setProperty($property, $value)
    {
        // Check to see if the value is a related model.
        if ($value instanceof self) {
            $value = $value->key();
        }

        $this->object->$property = $value;
    }

    /**
     * Sync the original object properties with the current.
     */
    protected function syncOriginal()
    {
        $this->original = clone $this->object;
    }
}
