<?php

namespace Pace;

use ArrayAccess;
use JsonSerializable;
use Pace\Model\Attachments;
use Pace\XPath\Builder;
use ReflectionMethod;
use UnexpectedValueException;

/**
 * @mixin Builder
 */
class Model implements ArrayAccess, JsonSerializable
{
    use Attachments;

    /**
     * The model's original attributes.
     *
     * @var array
     */
    protected array $original = [];

    /**
     * Auto-magically loaded "belongs to" relationships.
     *
     * @var static[]
     */
    protected array $relations = [];

    /**
     * Indicates if this model exists in Pace.
     *
     * @var bool
     */
    public bool $exists = false;

    /**
     * Create a new model instance.
     *
     * @param Client $client
     * @param string $type
     * @param array $attributes
     */
    public function __construct(protected Client $client, protected string $type, protected array $attributes = [])
    {
        $this->syncOriginal();
    }

    /**
     * Dynamically handle method calls.
     *
     * @param string $method
     * @param array $arguments
     * @return mixed
     */
    public function __call(string $method, array $arguments): mixed
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
    public function __get(string $name): mixed
    {
        return $this->getAttribute($name);
    }

    /**
     * Determine if the specified model property is set.
     *
     * @param string $name
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return !is_null($this->getAttribute($name));
    }

    /**
     * Set the specified model property.
     *
     * @param string $name
     * @param mixed $value
     */
    public function __set(string $name, mixed $value): void
    {
        $this->setAttribute($name, $value);
    }

    /**
     * Convert the instance to a string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return json_encode($this);
    }

    /**
     * Unset the specified model property.
     *
     * @param string $name
     */
    public function __unset(string $name): void
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
    public function belongsTo(string $relatedType, string $foreignKey): ?static
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
    public function create(array $attributes): static
    {
        $model = $this->newInstance($attributes);
        $model->save();

        return $model;
    }

    /**
     * Delete the model from the web service.
     *
     * @param string|null $keyName
     * @return true|null
     */
    public function delete(?string $keyName = null): ?bool
    {
        if ($this->exists) {
            $this->client->deleteObject($this->type, $this->key($keyName));
            $this->exists = false;

            return true;
        }

        return null;
    }

    /**
     * Persist the model in the web service as a duplicate and restore the model's attributes.
     *
     * @param int|string $newKey
     * @return Model|null
     */
    public function duplicate(mixed $newKey = null): ?static
    {
        if ($this->exists) {
            $attributes = $this->client->cloneObject($this->type, $this->original, $this->getDirty(), $newKey);

            $model = $this->newInstance($attributes);
            $model->exists = true;

            $this->restore();

            return $model;
        }

        return null;
    }

    /**
     * Find primary keys in the web service using a filter (and optionally sort).
     *
     * @param string $filter
     * @param array|null $sort
     * @param int|null $offset
     * @param int|null $limit
     * @param array $fields
     * @return KeyCollection
     */
    public function find(string $filter, ?array $sort = null, ?int $offset = null, ?int $limit = null, array $fields = []): KeyCollection
    {
        if (!empty($fields)) {
            if (is_null($offset)) {
                $offset = 0;
            }
            if (is_null($limit)) {
                $limit = 1000;
            }
        }

        $keys = $this->client->findObjects($this->type, $filter, $sort, $offset, $limit, $fields);

        return $this->newKeyCollection($keys);
    }

    /**
     * Refresh the attributes of the model from the web service.
     *
     * @param string|null $keyName
     * @return Model|null
     */
    public function fresh(?string $keyName = null): ?static
    {
        if (!$this->exists) {
            return null;
        }

        return $this->read($this->key($keyName));
    }

    /**
     * Get the attributes which have changed since the last sync.
     *
     * @return array
     */
    public function getDirty(): array
    {
        return array_diff_assoc($this->attributes, $this->original);
    }

    /**
     * Get the specified attribute or null if it does not exist.
     *
     * @param string $name
     * @return mixed
     */
    public function getAttribute(string $name): mixed
    {
        if ($this->hasAttribute($name)) {
            return $this->attributes[$name];
        }

        return null;
    }

    /**
     * Get the type of the model.
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Determine if the specified attribute exists.
     *
     * @param string $attribute
     * @return bool
     */
    public function hasAttribute(string $attribute): bool
    {
        return array_key_exists($attribute, $this->attributes);
    }

    /**
     * Fetch a "has many" relationship.
     *
     * @param string $relatedType
     * @param string $foreignKey
     * @param string|null $keyName
     * @return Builder
     */
    public function hasMany(string $relatedType, string $foreignKey, ?string $keyName = null): Builder
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
    public function isDirty(): bool
    {
        return $this->original !== $this->attributes;
    }

    /**
     * Join an array of keys into a compound key.
     *
     * @param array $keys
     * @return string
     */
    public function joinKeys(array $keys): string
    {
        return implode(':', $keys);
    }

    /**
     * Convert the model to a serializable array.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Get the model's primary key.
     *
     * @param string|null $keyName
     * @return mixed
     * @throws UnexpectedValueException if the key is null.
     */
    public function key(?string $keyName = null): mixed
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
    public function morphMany(string $relatedType, string $baseObject = 'baseObject', string $baseObjectKey = 'baseObjectKey', ?string $keyName = null): Builder
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
    public function newInstance(array $attributes = []): static
    {
        return new static($this->client, $this->type, $attributes);
    }

    /**
     * Determine if the specified offset exists.
     *
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->hasAttribute($offset);
    }

    /**
     * Get the value at the specified offset.
     *
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->getAttribute($offset);
    }

    /**
     * Set the value at the specified offset.
     *
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->setAttribute($offset, $value);
    }

    /**
     * Unset the specified offset.
     *
     * @param mixed $offset
     */
    public function offsetUnset(mixed $offset): void
    {
        $this->unsetAttribute($offset);
    }

    /**
     * Read a new model from the web service using the specified primary key.
     *
     * @param int|string $key
     * @return Model|null
     */
    public function read(mixed $key): ?static
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
    public function readOrFail(mixed $key): static
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
    public function save(): bool
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
    public function setAttribute(string $name, mixed $value): void
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
     * @param string|null $key
     * @return array
     */
    public function splitKey(?string $key = null): array
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
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * Unset the specified model attribute.
     *
     * @param string $name
     */
    public function unsetAttribute(string $name): void
    {
        unset($this->attributes[$name]);
    }

    /**
     * Get a compound key for a "belongs to" relationship.
     *
     * @param string $foreignKey
     * @return string
     */
    protected function getCompoundKey(string $foreignKey): string
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
     * @param string|null $keyName
     * @return array
     */
    protected function getCompoundKeyArray(string $foreignKey, ?string $keyName = null): array
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
    protected function getRelatedFromMethod(string $method): static|Builder|null
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
    protected function guessPrimaryKey(): string
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
    protected function isBuilderMethod(string $name): bool
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
    protected function isCompoundKey(mixed $key): bool
    {
        return strpos($key, ':') !== false;
    }

    /**
     * Create a new XPath builder.
     *
     * @return Builder
     */
    public function newBuilder(): Builder
    {
        return new Builder($this);
    }

    /**
     * Create a new key collection with the supplied keys.
     *
     * @param array $keys
     * @return KeyCollection
     */
    protected function newKeyCollection(array $keys): KeyCollection
    {
        foreach ($keys as $key) {
            if (is_object($key)) {
                return KeyCollection::fromValueObjects($this, $keys);
            }
            break;
        }

        return new KeyCollection($this, $keys);
    }

    /**
     * Determine if a "belongs to" relationship has already been loaded.
     *
     * @param string $relation
     * @return bool
     */
    protected function relationLoaded(string $relation): bool
    {
        return array_key_exists($relation, $this->relations);
    }

    /**
     * Restore the current model attributes from the original.
     */
    protected function restore(): void
    {
        $this->attributes = $this->original;
    }

    /**
     * Sync the original object attributes with the current.
     */
    protected function syncOriginal(): void
    {
        $this->original = $this->attributes;
    }
}
