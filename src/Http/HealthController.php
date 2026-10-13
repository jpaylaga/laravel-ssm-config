<?php

namespace Jpaylaga\SsmConfig\Http;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\JsonResponse;
use Jpaylaga\SsmConfig\Support\ConfigSource;
use Jpaylaga\SsmConfig\Support\ParameterMap;

/**
 * Reports whether the mapped config keys are populated, and where config came
 * from, for smoke checks after a deploy. Key names only; never a value.
 */
class HealthController
{
    public function __invoke(Repository $config, ParameterMap $map, ConfigSource $source): JsonResponse
    {
        $present = array_values(array_filter($map->keys(), fn (string $key) => $config->get($key) !== null));
        $missing = array_values(array_diff($map->keys(), $present));

        return new JsonResponse([
            'loaded' => $missing === [],
            'source' => $source->value,
            'keys' => $present,
            'missing' => $missing,
        ], $missing === [] ? 200 : 503);
    }
}
