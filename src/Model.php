<?php

namespace Pace;

use ArrayAccess;
use JsonSerializable;
use Pace\Model\Attachments;
use Pace\XPath\Builder;
use ReflectionMethod;
use UnexpectedValueException;

class Model implements ArrayAccess, JsonSerializable
{
    use Attachments;

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
     * The model's attributes.
     *
     * @var array
     */
    protected $attributes = [];

    /**
     * The model's original attributes.
     *
     * @var array
     */
    protected $original = [];

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
     * @param array $attributes
     */
    public function __construct(Client $client, string $type, array $attributes = [])
    {
        $this->client = $client;
        $this->type = $type;
        $this->attributes = $attributes;

        $this->syncOriginal();
    }

    /**
     * Dynamically handle method calls.
     *
     * @param string $method
     * @param array $arguments
     * @return mixed
     */
    public function __call($method, array $arguments)
    {
        if ($this->isBuilderMethod($method)) {
            return $this->newBuilder()->$method(...$arguments);
        }

        return $this->getRelatedFromMethod($method);
    }

    /**
     * Get the specified model property.
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->getAttribute($name);
    }

    /**
     * Determine if the specified model property is set.
     *
     * @param string $name
     * @return bool
     */
    public function __isset($name)
    {
        return !is_null($this->getAttribute($name));
    }

    /**
     * Set the specified model property.
     *
     * @param string $name
     * @param mixed $value
     */
    public function __set($name, $value)
    {
        $this->setAttribute($name, $value);
    }

    /**
     * Convert the instance to a string.
     *
     * @return string
     */
    public function __toString()
    {
        return json_encode($this);
    }

    /**
     * Unset the specified model property.
     *
     * @param string $name
     */
    public function __unset($name)
    {
        $this->unsetAttribute($name);
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
            $key = $this->getAttribute($foreignKey);
        }

        return $this->client->model($relatedType)->read($key);
    }

    /**
     * Create a new model from an array of attributes and persist it to the web service.
     *
     * @param array $attributes
     * @return Model
     */
    public function create(array $attributes)
    {
        $model = $this->newInstance($attributes);
        $model->save();

        return $model;
    }

    /**
     * Delete the model from the web service.
     *
     * @param string $keyName
     * @return bool|null
     */
    public function delete($keyName = null)
    {
        if ($this->exists) {
            $this->client->deleteObject($this->type, $this->key($keyName));
            $this->exists = false;

            return true;
        }
    }

    /**
     * Persist the model in the web service as a duplicate and restore the model's attributes.
     *
     * @param int|string $newKey
     * @return Model|null
     */
    public function duplicate($newKey = null)
    {
        if ($this->exists) {
            $attributes = $this->client->cloneObject($this->type, $this->original, $this->getDirty(), $newKey);

            $model = $this->newInstance($attributes);
            $model->exists = true;

            $this->restore();

            return $model;
        }
    }

    /**
     * Find primary keys in the web service using a filter (and optionally sort).
     *
     * @param string $filter
     * @param array|null $sort
     * @return KeyCollection
     */
    public function find($filter, $sort = null)
    {
        $keys = $this->client->findObjects($this->type, $filter, $sort);

        return $this->newKeyCollection($keys);
    }

    /**
     * Refresh the attributes of the model from the web service.
     *
     * @param string $keyName
     * @return Model|null
     */
    public function fresh($keyName = null)
    {
        if (!$this->exists) {
            return null;
        }

        $fresh = $this->read($this->key($keyName));

        return $fresh;
    }

    /**
     * Get the attributes which have changed since the last sync.
     *
     * @return array
     */
    public function getDirty()
    {
        return array_diff_assoc($this->attributes, $this->original);
    }

    /**
     * Get the specified attribute or null if it does not exist.
     *
     * @param string $name
     * @return mixed
     */
    public function getAttribute($name)
    {
        if ($this->hasAttribute($name)) {
            return $this->attributes[$name];
        }
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
     * Determine if the specified attribute exists.
     *
     * @param string $attribute
     * @return bool
     */
    public function hasAttribute($attribute)
    {
        return array_key_exists($attribute, $this->attributes);
    }

    /**
     * Fetch a "has many" relationship.
     *
     * @param string $relatedType
     * @param string $foreignKey
     * @param string $keyName
     * @return Builder
     */
    public function hasMany($relatedType, $foreignKey, $keyName = null)
    {
        $builder = $this->client->model($relatedType)->newBuilder();

        if ($this->isCompoundKey($foreignKey)) {
            foreach ($this->getCompoundKeyArray($foreignKey, $keyName) as $attribute => $value) {
                $builder->filter('@' . $attribute, $value);
            }
        } else {
            $builder->filter('@' . $foreignKey, $this->key($keyName));
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
        return $this->original !== $this->attributes;
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
        return $this->toArray();
    }

    /**
     * Get the model's primary key.
     *
     * @param string $keyName
     * @return string|int
     * @throws UnexpectedValueException if the key is null.
     */
    public function key($keyName = null)
    {
        $key = $this->getAttribute($keyName ?: $this->guessPrimaryKey());

        if ($key == null) {
            throw new UnexpectedValueException('Key must not be null.');
        }

        return $key;
    }

    /**
     * Fetch a "morph many" relationship.
     *
     * @param string $relatedType
     * @param string $baseObject
     * @param string $baseObjectKey
     * @param string|null $keyName
     * @return Builder
     */
    public function morphMany($relatedType, $baseObject = 'baseObject', $baseObjectKey = 'baseObjectKey', $keyName = null)
    {
        $builder = $this->client->model($relatedType)->newBuilder();

        $builder->filter('@' . $baseObject, $this->type);
        $builder->filter('@' . $baseObjectKey, $this->key($keyName));

        return $builder;
    }

    /**
     * Create a new model instance.
     *
     * @param array $attributes
     * @return Model
     */
    public function newInstance(array $attributes = [])
    {
        return new static($this->client, $this->type, $attributes);
    }

    /**
     * Determine if the specified offset exists.
     *
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return $this->hasAttribute($offset);
    }

    /**
     * Get the value at the specified offset.
     *
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->getAttribute($offset);
    }

    /**
     * Set the value at the specified offset.
     *
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        $this->setAttribute($offset, $value);
    }

    /**
     * Unset the specified offset.
     *
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        $this->unsetAttribute($offset);
    }

    /**
     * Read a new model from the web service using the specified primary key.
     *
     * @param int|string $key
     * @return Model
     */
    public function read($key)
    {
        // This is intentionally not strict. The web service considers
        // an integer 0 to be null and will respond with a fault.
        if ($key == null) {
            return null;
        }

        $attributes = $this->client->readObject($this->type, $key);

        if (is_null($attributes)) {
            return null;
        }

        $model = $this->newInstance($attributes);
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
        $model = $this->read($key);

        if (is_null($model)) {
            throw new ModelNotFoundException("$this->type [$key] does not exist.");
        }

        return $model;
    }

    /**
     * Persist the model in the web service.
     *
     * @return bool
     */
    public function save()
    {
        if ($this->exists) {
            // Update an existing object.
            $this->attributes = $this->client->updateObject($this->type, $this->attributes);

        } else {
            // Create a new object and fill default values.
            $this->attributes = $this->client->createObject($this->type, $this->attributes);
            $this->exists = true;
        }

        $this->syncOriginal();

        return true;
    }

    /**
     * Set the specified model attribute to the supplied value.
     *
     * @param string $name
     * @param mixed $value
     */
    public function setAttribute($name, $value)
    {
        // Check to see if the value is a related model.
        if ($value instanceof self) {
            $value = $value->key();
        }

        $this->attributes[$name] = $value;
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
     * Convert the instance to an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->attributes;
    }

    /**
     * Unset the specified model attribute.
     *
     * @param string $name
     */
    public function unsetAttribute($name)
    {
        unset($this->attributes[$name]);
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
            $keys[] = $this->getAttribute($key);
        }

        return $this->joinKeys($keys);
    }

    /**
     * Get a compound key array for a "has many" relationship.
     *
     * @param string $foreignKey
     * @param string $keyName
     * @return array
     */
    protected function getCompoundKeyArray($foreignKey, $keyName)
    {
        return array_combine(
            $this->splitKey($foreignKey),
            $this->splitKey($this->key($keyName))
        );
    }

    /**
     * Auto-magically fetch relationships.
     *
     * @param string $method
     * @return Builder|Model|null
     */
    protected function getRelatedFromMethod($method)
    {
        // If the called method name exists as an attribute on the model,
        // assume it is the camel-cased related type and the attribute
        // contains the foreign key for a "belongs to" relationship.
        if ($this->hasAttribute($method)) {
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
     * Attempt to guess the primary key name.
     *
     * @return string
     */
    protected function guessPrimaryKey()
    {
        if ($keyName = Type::keyName($this->type)) {
            return $keyName;
        }

        if ($this->hasAttribute(Client::PRIMARY_KEY)) {
            return Client::PRIMARY_KEY;
        }

        if ($this->hasAttribute('id')) {
            return 'id';
        }

        return Type::camelize($this->type);
    }

    /**
     * Determine if a dynamic call should be passed to the XPath builder class.
     *
     * @param string $name
     * @return bool
     */
    protected function isBuilderMethod($name)
    {
        if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
            if (method_exists(Builder::class, $name)) {
                $reflection = new ReflectionMethod(Builder::class, $name);
                return $reflection->isPublic();
            } else {
                return false;
            }
        } else {
            return method_exists(Builder::class, $name) && is_callable([Builder::class, $name]);
        }
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
    public function newBuilder()
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
     * Restore the current model attributes from the original.
     */
    protected function restore()
    {
        $this->attributes = $this->original;
    }

    /**
     * Sync the original object attributes with the current.
     */
    protected function syncOriginal()
    {
        $this->original = $this->attributes;
    }
}
