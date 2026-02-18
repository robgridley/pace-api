<?php

namespace Pace\Soap;

use Pace\Contracts\Soap\Factory as FactoryContract;
use Pace\Contracts\Soap\TypeMapping;

class Factory implements FactoryContract
{
    /**
     * SOAP client options.
     *
     * @var array
     */
    protected array $options = [];

    /**
     * Type mappings.
     *
     * @var array
     */
    protected array $types = [];

    /**
     * Add a new SOAP to PHP type mapping.
     *
     * @param TypeMapping $mapping
     */
    public function addTypeMapping(TypeMapping $mapping): void
    {
        $this->types[$mapping->getTypeNamespace() . ':' . $mapping->getTypeName()] = $mapping;
    }

    /**
     * Create a new SoapClient instance.
     *
     * @param string $wsdl
     * @return SoapClient
     */
    public function make(string $wsdl): SoapClient
    {
        return new SoapClient($wsdl, $this->getOptions());
    }

    /**
     * Set the specified SOAP client option.
     *
     * @param string $key
     * @param mixed $value
     */
    public function setOption(string $key, mixed $value): void
    {
        $this->options[$key] = $value;
    }

    /**
     * Bulk set the specified SOAP client options.
     *
     * @param array $options
     */
    public function setOptions(array $options): void
    {
        foreach ($options as $key => $value) {
            $this->setOption($key, $value);
        }
    }

    /**
     * Get the SOAP client options.
     *
     * @return array
     */
    protected function getOptions(): array
    {
        return array_merge($this->options, [
            'typemap' => $this->getTypeMappings()
        ]);
    }

    /**
     * Convert the supplied type mapping instance to an array.
     *
     * @param TypeMapping $type
     * @return array
     */
    protected function getTypeMapping(TypeMapping $type): array
    {
        return [
            'type_name' => $type->getTypeName(),
            'type_ns' => $type->getTypeNamespace(),
            'from_xml' => function ($xml) use ($type) {
                return $type->fromXml($xml);
            },
            'to_xml' => function ($php) use ($type) {
                return $type->toXml($php);
            }
        ];
    }

    /**
     * Get the mappings for the SOAP client.
     *
     * @return array
     */
    protected function getTypeMappings(): array
    {
        $types = [];

        foreach ($this->types as $type) {
            $types[] = $this->getTypeMapping($type);
        }

        return $types;
    }
}
