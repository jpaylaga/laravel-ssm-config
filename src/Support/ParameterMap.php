<?php

namespace Jpaylaga\SsmConfig\Support;

/** Which parameter populates which config keys (the "map" in config/ssm.php). */
final class ParameterMap
{
    /** @var array<string, list<string>> */
    private array $map = [];

    /** @param array<string, string|array<string>> $map */
    public function __construct(array $map)
    {
        foreach ($map as $parameter => $keys) {
            $this->map[$parameter] = array_values((array) $keys);
        }
    }

    /** @return list<string> */
    public function parameters(): array
    {
        return array_keys($this->map);
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_merge([], ...array_values($this->map));
    }

    /** @return list<string> */
    public function keysFor(string $parameter): array
    {
        return $this->map[$parameter] ?? [];
    }

    public function has(string $parameter): bool
    {
        return isset($this->map[$parameter]);
    }
}
