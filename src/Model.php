<?php

namespace Pace;

use ArrayAccess;
use JsonSerializable;
use Pace\XPath\Builder;
use InvalidArgumentException;
use UnexpectedValueException;

class Model implements ArrayAccess, JsonSerializable
{
    /**
     * The model type.
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
    protected $properties;

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
     * @param string $type
     * @param object|array $properties
     */
    public function __construct(Client $client, $type, $properties = [])
    {
        if (!preg_match('/^([A-Z]+[a-z]*)+$/', $type)) {
            throw new InvalidArgumentException('Type must be CapitalizedWords.');
        }

        $this->client = $client;
        $this->type = $type;
        $this->properties = (object)$properties;

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
        unset($this->properties->$property);
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
     * Create a new model from an array of properties and persist it to the web service.
     *
     * @param array $properties
     * @return Model
     */
    public function create(array $properties)
    {
        $model = $this->newInstance($properties);
        $model->save();

        return $model;
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
            $this->client->deleteObject($this->type, $this->key($primaryKey));
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
            $response = $this->client->cloneObject($this->type, $this->original, $this->getDirty(), $newPrimaryKey);

            $model = $this->newInstance($response);
            $model->exists = true;

            $this->restore();

            return $model;
        }
    }

    /**
     * Find primary keys in the web service using a filter (and optionally sort).
     *
     * @param string $filter
     * @param array $sort
     * @return KeyCollection
     */
    public function find($filter = null, $sort = null)
    {
        $keys = $this->client->findObjects($this->type, $filter, $sort);

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

        $fresh = $this->read($this->key($primaryKey));

        return $fresh;
    }

    /**
     * Get the object properties which have changed since the last sync.
     *
     * @return object
     */
    public function getDirty()
    {
        return (object)array_diff_assoc((array)$this->properties, (array)$this->original);
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
        return $this->original != $this->properties;
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
        return (array)$this->properties;
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
     * @param object|array $properties
     * @return Model
     */
    public function newInstance($properties = [])
    {
        return new static($this->client, $this->type, $properties);
    }

    /**
     * Determine is the specified model property exists.
     *
     * @param string $property
     * @return bool
     */
    public function offsetExists($property)
    {
        return isset($this->$property);
    }

    /**
     * Get the specified model property.
     *
     * @param string $property
     * @return mixed
     */
    public function offsetGet($property)
    {
        return $this->$property;
    }

    /**
     * Set the specified model property to the supplied value.
     *
     * @param string $property
     * @param mixed $value
     */
    public function offsetSet($property, $value)
    {
        $this->$property = $value;
    }

    /**
     * Destroy the specified model property.
     *
     * @param string $property
     */
    public function offsetUnset($property)
    {
        unset($this->$property);
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

        $response = $this->client->readObject($this->type, $key);

        if (empty($response)) {
            return null;
        }

        $model = $this->newInstance($response);
        $model->exists = true;

        return $model;
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
     * @return bool|int Return primary key if it is named 'id', or just true for save of existing object 
     */
    public function save()
    {
        if ($this->exists) {
            // Update an existing object in Pace.
            $this->properties = $this->client->updateObject($this->type, $this->properties);

        } else {
            // Create a new object in Pace and fill default values.
            $this->properties = $this->client->createObject($this->type, $this->properties);
            //print_r($this->properties);
            $this->exists = true;
            // TOOD deal with 'job','jobPart' (and others if any) where the primary key is not 'id'
            if(isset($this->properties->id)){
                $id = $this->properties->id;
            }
        }

        $this->syncOriginal();

        if(isset($id)) return $id;
        return true;
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
        if (property_exists($this->properties, $property)) {
            return $this->properties->$property;
        }

        // Next, check if a user defined field exists.
        if (property_exists($this->properties, "U_$property")) {
            return $this->properties->{"U_$property"};
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
                $relatedType = Type::modelify($method);
                $this->relations[$method] = $this->belongsTo($relatedType, $method);
            }

            return $this->relations[$method];
        }

        // Otherwise, the called method name should be a pluralized,
        // camel-cased related type for a "has many" relationship.
        $relatedType = Type::modelify(Type::singularize($method));
        $foreignKey = Type::camelize($this->type);
        return $this->hasMany($relatedType, $foreignKey);
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

        return Type::camelize($this->type);
    }

    /**
     * Determine if the specified property exists.
     *
     * @param string $property
     * @return bool
     */
    protected function hasProperty($property)
    {
        return property_exists($this->properties, $property) || property_exists($this->properties, "U_$property");
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
        $this->properties = clone $this->original;
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

        $this->properties->$property = $value;
    }

    /**
     * Sync the original object properties with the current.
     */
    protected function syncOriginal()
    {
        $this->original = clone $this->properties;
    }
}
