<?php

namespace Pace;

use ArrayAccess;
use Countable;
use Iterator;
use JsonSerializable;
use OutOfBoundsException;
use RuntimeException;

class KeyCollection implements ArrayAccess, Countable, Iterator, JsonSerializable
{
    /**
     * Cached reads.
     *
     * @var array
     */
    protected array $readModels = [];

    /**
     * Create a new key collection instance.
     *
     * @param Model $model
     * @param array $keys
     */
    public function __construct(protected Model $model, protected array $keys)
    {
    }

    /**
     * Convert this instance to its string representation.
     *
     * @return string
     */
    public function __toString(): string
    {
        return json_encode($this);
    }

    /**
     * Read all of the keys.
     *
     * @return Model[]
     */
    public function all(): array
    {
        return iterator_to_array($this);
    }

    /**
     * Count the number of keys.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->keys);
    }

    /**
     * Read the current key.
     *
     * @return Model|null
     */
    public function current(): ?Model
    {
        return $this->read($this->key());
    }

    /**
     * Get the keys which are not present in the supplied keys.
     *
     * @param mixed $keys
     * @return KeyCollection
     */
    public function diff(mixed $keys): static
    {
        return $this->fresh(array_diff($this->keys, ($keys instanceof static) ? $keys->keys() : (array)$keys));
    }

    /**
     * Filter the keys in the collection using a callback.
     *
     * @param callable $callback
     * @return KeyCollection
     */
    public function filterKeys(callable $callback): static
    {
        return $this->fresh(array_values(array_filter($this->keys, $callback)));
    }

    /**
     * Read only the first key.
     *
     * @return Model|null
     */
    public function first(): ?Model
    {
        $key = reset($this->keys);

        return $this->read($key);
    }

    /**
     * Get the model for the specified key.
     *
     * @param string|int $key
     * @return Model|null
     * @throws OutOfBoundsException if the key does not exist.
     */
    public function get(mixed $key): ?Model
    {
        if (!$this->has($key)) {
            throw new OutOfBoundsException("The key '$key' does not exist");
        }

        return $this->read($key);
    }

    /**
     * Check if the specified key exists.
     *
     * @param mixed $key
     * @return bool
     */
    public function has(mixed $key): bool
    {
        return in_array($key, $this->keys, true);
    }

    /**
     * Determine if the key collection is empty.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->keys);
    }

    /**
     * Convert this instance to a serializable array.
     *
     * @return array
     */
    function jsonSerialize(): array
    {
        return $this->all();
    }

    /**
     * Get the current key.
     *
     * @return mixed
     */
    public function key(): mixed
    {
        return current($this->keys);
    }

    /**
     * Get all keys.
     *
     * @return array
     */
    public function keys(): array
    {
        return $this->keys;
    }

    /**
     * Read only the last key.
     *
     * @return Model|null
     */
    public function last(): ?Model
    {
        $keys = array_reverse($this->keys);

        return $this->read(reset($keys));
    }

    /**
     * Move forward to the next key.
     */
    public function next(): void
    {
        next($this->keys);
    }

    /**
     * Check if the specified key exists.
     *
     * @param mixed $key
     * @return bool
     */
    public function offsetExists(mixed $key): bool
    {
        return $this->has($key);
    }

    /**
     * Read the specified key.
     *
     * @param mixed $key
     * @return Model|null
     */
    public function offsetGet(mixed $key): ?Model
    {
        return $this->get($key);
    }

    /**
     * Set the value for the specified key.
     *
     * @param mixed $key
     * @param mixed $value
     * @throws RuntimeException
     */
    public function offsetSet(mixed $key, mixed $value): void
    {
        $class = get_class($this);

        throw new RuntimeException("Unable to set key '$key': $class is immutable");
    }

    /**
     * Unset the value at the specified index.
     *
     * @param mixed $key
     * @throws RuntimeException
     */
    public function offsetUnset(mixed $key): void
    {
        $class = get_class($this);

        throw new RuntimeException("Unable to unset key '$key': $class is immutable");
    }

    /**
     * Paginate the keys.
     *
     * @param int $page
     * @param int $perPage
     * @return KeyCollection
     */
    public function paginate(int $page, int $perPage = 25): static
    {
        $offset = max($page - 1, 0) * $perPage;

        return $this->slice($offset, $perPage);
    }

    /**
     * Get the values of a given key.
     *
     * @param string $value
     * @param string|null $key
     * @return array
     */
    public function pluck(string $value, ?string $key = null): array
    {
        $models = $this->all();
        $results = [];

        foreach ($models as $model) {
            if (!is_null($key)) {
                $results[$model->$key] = $model->$value;
            } else {
                $results[] = $model->$value;
            }
        }

        return $results;
    }

    /**
     * Rewind to the first key.
     */
    public function rewind(): void
    {
        reset($this->keys);
    }

    /**
     * Add a portion of the keys to a new collection.
     *
     * @param int $offset
     * @param int|null $length
     * @return KeyCollection
     */
    public function slice(int $offset, ?int $length = null): static
    {
        return $this->fresh(array_slice($this->keys, $offset, $length));
    }

    /**
     * Check if the current position is valid.
     *
     * @return bool
     */
    public function valid(): bool
    {
        return $this->key() !== false;
    }

    /**
     * Create a new key collection instance.
     *
     * @param array $keys
     * @return KeyCollection
     */
    protected function fresh(array $keys): static
    {
        return new static($this->model, $keys);
    }

    /**
     * Get the model for the specified key.
     *
     * @param mixed $key
     * @return Model|null
     */
    protected function read(mixed $key): ?Model
    {
        if ($key === false) {
            return null;
        }

        if (!array_key_exists($key, $this->readModels)) {
            $this->readModels[$key] = $this->model->read($key);
        }

        return $this->readModels[$key];
    }
}
