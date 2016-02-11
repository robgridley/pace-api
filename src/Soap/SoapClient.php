<?php

namespace Pace\Soap;

use SoapClient as BaseSoapClient;

class SoapClient extends BaseSoapClient
{
    /**
     * Cached types.
     *
     * @var array
     */
    protected $types = [];

    /**
     * Get a multidimensional array of types.
     *
     * @param string $name
     * @return array
     */
    public function getTypes($name = null)
    {
        if (empty($this->types)) {
            $structures = $this->__getTypes();

            foreach ($structures as $structure) {
                $type = $this->parseComplexType($structure);

                if ($type == null) {
                    continue;
                }

                $this->types[$type] = $this->parsePropertyTypes($structure);
            }
        }

        return $name == null ? $this->types : $this->types[$name];
    }

    /**
     * Get the complex type from a structure.
     *
     * @param string $structure
     * @return string|null
     */
    protected function parseComplexType($structure)
    {
        if (preg_match('/struct (\w+) {/', $structure, $matches)) {
            return $matches[1];
        }
    }

    /**
     * Get the property types from a structure.
     *
     * @param string $structure
     * @return array
     */
    protected function parsePropertyTypes($structure)
    {
        $rows = explode("\n", $structure);

        $properties = [];

        foreach (array_slice($rows, 1) as $row) {
            if (preg_match('/ (\w+) (\w+);/', $row, $matches)) {
                $properties[$matches[2]] = $matches[1];
            }
        }

        return $properties;
    }
}
