<?php

namespace Pace\XPath;

use Closure;
use DateTime;
use InvalidArgumentException;
use Pace\KeyCollection;
use Pace\Model;
use Pace\ModelNotFoundException;

class Builder
{
    /**
     * Valid operators.
     *
     * @var array
     */
    protected array $operators = ['=', '!=', '<', '>', '<=', '>='];

    /**
     * Valid functions.
     *
     * @var array
     */
    protected array $functions = ['contains', 'starts-with'];

    /**
     * The filters.
     *
     * @var array
     */
    protected array $filters = [];

    /**
     * The sorts.
     *
     * @var array
     */
    protected array $sorts = [];

    /**
     * The fields to load.
     *
     * @var array
     */
    protected array $fields = [];

    /**
     * The result offset.
     *
     * @var int
     */
    protected int $offset = 0;

    /**
     * The results limit.
     *
     * @var int|null
     */
    protected ?int $limit = null;

    /**
     * Create a new instance.
     *
     * @param Model|null $model
     */
    public function __construct(protected ?Model $model = null)
    {
    }

    /**
     * Add a "contains" filter.
     *
     * @param string $xpath
     * @param mixed $value
     * @param string $boolean
     * @return $this
     */
    public function contains(string $xpath, mixed $value = null, string $boolean = 'and'): static
    {
        return $this->filter($xpath, 'contains', $value, $boolean);
    }

    /**
     * Add an "or contains" filter.
     *
     * @param string $xpath
     * @param mixed $value
     * @return $this
     */
    public function orContains(string $xpath, mixed $value = null): static
    {
        return $this->filter($xpath, 'contains', $value, 'or');
    }

    /**
     * Add a filter.
     *
     * @param string|Closure $xpath
     * @param mixed $operator
     * @param mixed $value
     * @param string $boolean
     * @return $this
     */
    public function filter(string|Closure $xpath, mixed $operator = null, mixed $value = null, string $boolean = 'and'): static
    {
        if ($xpath instanceof Closure) {
            return $this->nestedFilter($xpath, $boolean);
        }

        if ($value === null && !$this->isOperator($operator)) {
            list($value, $operator) = [$operator, '='];
        }

        if (!$this->isOperator($operator) && !$this->isFunction($operator)) {
            throw new InvalidArgumentException("Operator '$operator' is not supported");
        }

        $this->filters[] = compact('xpath', 'operator', 'value', 'boolean');

        return $this;
    }

    /**
     * Perform the find request.
     *
     * @return KeyCollection
     */
    public function find(): KeyCollection
    {
        return $this->model->find($this->toXPath(), $this->toXPathSort(), $this->offset, $this->limit, $this->toFieldDescriptor());
    }

    /**
     * Get the first matching model.
     *
     * @return Model|null
     */
    public function first(): ?Model
    {
        return $this->find()->first();
    }

    /**
     * Get the first matching model or throw an exception.
     *
     * @return Model
     * @throws ModelNotFoundException
     */
    public function firstOrFail(): Model
    {
        $result = $this->first();

        if (is_null($result)) {
            throw new ModelNotFoundException("No filtered results for model [{$this->model->getType()}].");
        }

        return $result;
    }

    /**
     * Get the first matching model or a new instance.
     *
     * @return Model
     */
    public function firstOrNew(): Model
    {
        return $this->first() ?: $this->model->newInstance();
    }

    /**
     * A more "Eloquent" alias for find().
     *
     * @return KeyCollection
     */
    public function get(): KeyCollection
    {
        return $this->find();
    }

    /**
     * Load the specified fields.
     *
     * @param array $fields
     * @return $this
     */
    public function load(array $fields): static
    {
        foreach ($fields as $key => $xpath) {
            if (is_int($key)) {
                $key = ltrim($xpath, '@');
            }
            $this->fields[$key] = $xpath;
        }

        return $this;
    }

    /**
     * Set the result offset.
     *
     * @param int $offset
     * @return $this
     */
    public function offset(int $offset): static
    {
        $this->offset = $offset;

        return $this;
    }

    /**
     * Set the results limit.
     *
     * @param int $limit
     * @return $this
     */
    public function limit(int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * Paginate the results.
     *
     * @param int $page
     * @param int $perPage
     * @return $this
     */
    public function paginate(int $page, int $perPage = 25): static
    {
        $offset = max($page - 1, 0) * $perPage;

        return $this->offset($offset)->limit($perPage);
    }

    /**
     * Add an "in" filter.
     *
     * @param string $xpath
     * @param array $values
     * @param string $boolean
     * @return $this
     */
    public function in(string $xpath, array $values, string $boolean = 'and'): static
    {
        return $this->filter(function ($builder) use ($xpath, $values) {
            foreach ($values as $value) {
                $builder->filter($xpath, '=', $value, 'or');
            }
        }, null, null, $boolean);
    }

    /**
     * Add an "or in" filter.
     *
     * @param string $xpath
     * @param array $values
     * @return $this
     */
    public function orIn(string $xpath, array $values): static
    {
        return $this->in($xpath, $values, 'or');
    }

    /**
     * Add a nested filter using a callback.
     *
     * @param Closure $callback
     * @param string $boolean
     * @return $this
     */
    public function nestedFilter(Closure $callback, string $boolean = 'and'): static
    {
        $builder = new static;

        $callback($builder);

        $this->filters[] = compact('builder', 'boolean');

        return $this;
    }

    /**
     * Add an "or" filter.
     *
     * @param string|Closure $xpath
     * @param mixed $operator
     * @param mixed $value
     * @return $this
     */
    public function orFilter(string|Closure $xpath, mixed $operator = null, mixed $value = null): static
    {
        return $this->filter($xpath, $operator, $value, 'or');
    }

    /**
     * Add a "starts-with" filter.
     *
     * @param string $xpath
     * @param mixed $value
     * @param string $boolean
     * @return $this
     */
    public function startsWith(string $xpath, mixed $value = null, string $boolean = 'and'): static
    {
        return $this->filter($xpath, 'starts-with', $value, $boolean);
    }

    /**
     * Add an "or starts-with" filter.
     *
     * @param string $xpath
     * @param mixed $value
     * @return $this
     */
    public function orStartsWith(string $xpath, mixed $value = null): static
    {
        return $this->filter($xpath, 'starts-with', $value, 'or');
    }

    /**
     * Add a sort.
     *
     * @param string $xpath
     * @param bool $descending
     * @return $this
     */
    public function sort(string $xpath, bool $descending = false): static
    {
        $this->sorts[] = compact('xpath', 'descending');

        return $this;
    }

    /**
     * Get the XPath filter expression.
     *
     * @return string
     */
    public function toXPath(): string
    {
        $xpath = [];

        foreach ($this->filters as $filter) {

            if (isset($filter['builder'])) {
                $xpath[] = $this->compileNested($filter);

            } elseif ($this->isFunction($filter['operator'])) {
                $xpath[] = $this->compileFunction($filter);

            } else {
                $xpath[] = $this->compileFilter($filter);
            }
        }

        return $this->stripLeadingBoolean(implode(' ', $xpath));
    }

    /**
     * Get the XPath sort array.
     *
     * @return array|null
     */
    public function toXPathSort(): ?array
    {
        return count($this->sorts) ? ['XPathDataSort' => $this->sorts] : null;
    }

    /**
     * Get the field descriptor array.
     *
     * @return array
     */
    public function toFieldDescriptor(): array
    {
        return array_map(function (string $name, string $xpath) {
            return [
                'name' => $name,
                'xpath' => $xpath,
            ];
        }, array_keys($this->fields), array_values($this->fields));
    }

    /**
     * Compile a simple filter.
     *
     * @param array $filter
     * @return string
     */
    protected function compileFilter(array $filter): string
    {
        return sprintf('%s %s %s %s',
            $filter['boolean'], $filter['xpath'], $filter['operator'], $this->value($filter['value']));
    }

    /**
     * Compile a function filter.
     *
     * @param array $filter
     * @return string
     */
    protected function compileFunction(array $filter): string
    {
        return sprintf('%s %s(%s, %s)',
            $filter['boolean'], $filter['operator'], $filter['xpath'], $this->value($filter['value']));
    }

    /**
     * Compile a nested filter.
     *
     * @param array $filter
     * @return string
     */
    protected function compileNested(array $filter): string
    {
        return sprintf('%s (%s)', $filter['boolean'], $filter['builder']->toXPath());
    }

    /**
     * Check if an operator is a valid function.
     *
     * @param mixed $operator
     * @return bool
     */
    protected function isFunction(mixed $operator): bool
    {
        return in_array($operator, $this->functions, true);
    }

    /**
     * Check if an operator is a valid operator.
     *
     * @param mixed $operator
     * @return bool
     */
    protected function isOperator(mixed $operator): bool
    {
        return in_array($operator, $this->operators, true);
    }

    /**
     * Strip the leading boolean from the expression.
     *
     * @param string $xpath
     * @return string
     */
    protected function stripLeadingBoolean(string $xpath): string
    {
        return preg_replace('/^and |^or /', '', $xpath);
    }

    /**
     * Get the XPath value for a PHP native type.
     *
     * @param mixed $value
     * @return string
     */
    protected function value(mixed $value): string
    {
        return match (true) {
            $value instanceof DateTime => $this->date($value),
            is_int($value), is_float($value) => (string)$value,
            is_bool($value) => $value ? '\'true\'' : '\'false\'',
            default => "\"$value\"",
        };
    }

    /**
     * Convert DateTime instance to XPath date function.
     *
     * @param DateTime $dt
     * @return string
     */
    protected function date(DateTime $dt): string
    {
        return $dt->format('\d\a\t\e(Y, n, j)');
    }
}
