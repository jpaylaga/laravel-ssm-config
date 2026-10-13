<?php

namespace Jpaylaga\SsmConfig\Steps;

use Illuminate\Contracts\Config\Repository;

/** Writes mapped values into the running application's config repository. */
final class ApplyConfig
{
    public function __construct(private Repository $config) {}

    /** @param array<string, string> $values config key => value */
    public function __invoke(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->config->set($key, $value);
        }
    }
}
