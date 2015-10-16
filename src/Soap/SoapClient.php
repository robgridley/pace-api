<?php

namespace Pace\Soap;

class SoapClient extends \SoapClient
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
     * @return array
     */
    public function getTypes()
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

        return $this->types;
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
