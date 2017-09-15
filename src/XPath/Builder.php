<?php

namespace Pace\XPath;

use Closure;
use DateTime;
use Pace\Model;
use InvalidArgumentException;
use Pace\ModelNotFoundException;

class Builder
{
    /**
     * Valid operators.
     *
     * @var array
     */
    protected $operators = ['=', '!=', '<', '>', '<=', '>='];

    /**
     * Valid functions.
     *
     * @var array
     */
    protected $functions = ['contains', 'starts-with'];

    /**
     * The filters.
     *
     * @var array
     */
    protected $filters = [];

    /**
     * The sorts.
     *
     * @var array
     */
    protected $sorts = [];

    /**
     * The Pace model instance to perform the find request on.
     *
     * @var Model
     */
    protected $model;

    /**
     * Create a new instance.
     *
     * @param Model $model
     */
    public function __construct(Model $model = null)
    {
        $this->model = $model;
    }

    /**
     * Add a "contains" filter.
     *
     * @param string $xpath
     * @param mixed $value
     * @param string $boolean
     * @return self
     */
    public function contains($xpath, $value = null, $boolean = 'and')
    {
        return $this->filter($xpath, 'contains', $value, $boolean);
    }

    /**
     * Add an "or contains" filter.
     *
     * @param string $xpath
     * @param mixed $value
     * @return self
     */
    public function orContains($xpath, $value = null)
    {
        return $this->filter($xpath, 'contains', $value, 'or');
    }

    /**
     * Add a filter.
     *
     * @param string $xpath
     * @param string $operator
     * @param mixed $value
     * @param string $boolean
     * @return self
     */
    public function filter($xpath, $operator = null, $value = null, $boolean = 'and')
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
     * @return \Pace\KeyCollection
     */
    public function find()
    {
        return $this->model->find($this->toXPath(), $this->toXPathSort());
    }

    /**
     * Get the first matching model.
     *
     * @return Model|null
     */
    public function first()
    {
        return $this->find()->first();
    }

    /**
     * Get the first matching model or throw an exception.
     *
     * @return Model
     * @throws ModelNotFoundException
     */
    public function firstOrFail()
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
    public function firstOrNew()
    {
        return $this->first() ?: $this->model->newInstance();
    }

    /**
     * A more "Eloquent" alias for find().
     *
     * @return \Pace\KeyCollection
     */
    public function get()
    {
        return $this->find();
    }

    /**
     * Add a nested filter using a callback.
     *
     * @param Closure $callback
     * @param string $boolean
     * @return self
     */
    public function nestedFilter(Closure $callback, $boolean = 'and')
    {
        $builder = new static;

        $callback($builder);

        $this->filters[] = compact('builder', 'boolean');

        return $this;
    }

    /**
     * Add an "or" filter.
     *
     * @param string $xpath
     * @param string $operator
     * @param mixed $value
     * @return self
     */
    public function orFilter($xpath, $operator = null, $value = null)
    {
        return $this->filter($xpath, $operator, $value, 'or');
    }

    /**
     * Add a "starts-with" filter.
     *
     * @param string $xpath
     * @param mixed $value
     * @param string $boolean
     * @return self
     */
    public function startsWith($xpath, $value = null, $boolean = 'and')
    {
        return $this->filter($xpath, 'starts-with', $value, $boolean);
    }

    /**
     * Add an "or starts-with" filter.
     *
     * @param string $xpath
     * @param mixed $value
     * @return self
     */
    public function orStartsWith($xpath, $value = null)
    {
        return $this->filter($xpath, 'starts-with', $value, 'or');
    }

    /**
     * Add a sort.
     *
     * @param string $xpath
     * @param bool $descending
     * @return self
     */
    public function sort($xpath, $descending = false)
    {
        $this->sorts[] = compact('xpath', 'descending');

        return $this;
    }

    /**
     * Get the XPath filter expression.
     *
     * @return string
     */
    public function toXPath()
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
    public function toXPathSort()
    {
        return count($this->sorts) ? ['XPathDataSort' => $this->sorts] : null;
    }

    /**
     * Compile a simple filter.
     *
     * @param array $filter
     * @return string
     */
    protected function compileFilter(array $filter)
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
    protected function compileFunction(array $filter)
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
    protected function compileNested(array $filter)
    {
        return sprintf('%s (%s)', $filter['boolean'], $filter['builder']->toXPath());
    }

    /**
     * Check if an operator is a valid function.
     *
     * @param string $operator
     * @return bool
     */
    protected function isFunction($operator)
    {
        return in_array($operator, $this->functions, true);
    }

    /**
     * Check if an operator is a valid operator.
     *
     * @param string $operator
     * @return bool
     */
    protected function isOperator($operator)
    {
        return in_array($operator, $this->operators, true);
    }

    /**
     * Strip the leading boolean from the expression.
     *
     * @param string $xpath
     * @return string
     */
    protected function stripLeadingBoolean($xpath)
    {
        return preg_replace('/^and |^or /', '', $xpath);
    }

    /**
     * Get the XPath value for a PHP native type.
     *
     * @param mixed $value
     * @return string
     */
    protected function value($value)
    {
        switch (true) {
            case ($value instanceof DateTime):
                return $this->date($value);

            case (is_int($value)):
            case (is_float($value)):
                return (string)$value;

            case (is_bool($value)):
                return $value ? '\'true\'' : '\'false\'';

            default:
                return "\"$value\"";
        }
    }

    /**
     * Convert DateTime instance to XPath date function.
     *
     * @param DateTime $dt
     * @return string
     */
    protected function date(DateTime $dt)
    {
        return $dt->format('\d\a\t\e(Y, n, j)');
    }
}
