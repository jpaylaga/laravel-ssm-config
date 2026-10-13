<?php

namespace Jpaylaga\SsmConfig\Steps;

use Illuminate\Filesystem\Filesystem;
use Jpaylaga\SsmConfig\Support\DotEnvFile;
use Jpaylaga\SsmConfig\Support\ParameterMap;

/**
 * Writes the mapped parameters into a .env file for local development. Existing
 * lines for the same keys are replaced in place; everything else is kept.
 */
final class WriteDotEnv
{
    public function __construct(
        private ParameterMap $map,
        private Filesystem $files,
    ) {}

    /**
     * @param  array<string, string>  $parameters
     * @return list<string> the keys written
     */
    public function __invoke(array $parameters, string $path): array
    {
        $values = array_filter($parameters, fn (string $name) => $this->map->has($name), ARRAY_FILTER_USE_KEY);

        $current = $this->files->exists($path) ? $this->files->get($path) : '';

        $this->files->put($path, DotEnvFile::update($current, $values));

        return array_keys($values);
    }
}
