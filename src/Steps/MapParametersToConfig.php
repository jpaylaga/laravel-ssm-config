<?php

namespace Jpaylaga\SsmConfig\Steps;

use Jpaylaga\SsmConfig\Support\ParameterMap;

/** Turns fetched parameters into config values, keeping only the mapped ones. */
final class MapParametersToConfig
{
    public function __construct(private ParameterMap $map) {}

    /**
     * @param  array<string, string>  $parameters
     * @return array<string, string> config key => value
     */
    public function __invoke(array $parameters): array
    {
        $values = [];

        foreach ($parameters as $name => $value) {
            foreach ($this->map->keysFor($name) as $key) {
                $values[$key] = $value;
            }
        }

        return $values;
    }
}
