<?php

namespace Jpaylaga\SsmConfig\Steps;

use Illuminate\Contracts\Config\Repository;
use Jpaylaga\SsmConfig\Support\ParameterMap;

/**
 * Compares live parameters with the running config, one row per mapped key.
 * Rows carry a status only, never a value, so the report is safe to log.
 */
final class DiffParameters
{
    public const SAME = 'same';

    public const CHANGED = 'changed';

    public const MISSING_IN_SSM = 'missing in SSM';

    public const MISSING_IN_CONFIG = 'missing in config';

    public function __construct(
        private ParameterMap $map,
        private Repository $config,
    ) {}

    /**
     * @param  array<string, string>  $parameters
     * @return list<array{parameter: string, key: string, status: string}>
     */
    public function __invoke(array $parameters): array
    {
        $rows = [];

        foreach ($this->map->parameters() as $parameter) {
            foreach ($this->map->keysFor($parameter) as $key) {
                $rows[] = [
                    'parameter' => $parameter,
                    'key' => $key,
                    'status' => $this->status($parameters[$parameter] ?? null, $this->config->get($key)),
                ];
            }
        }

        return $rows;
    }

    private function status(?string $inSsm, mixed $inConfig): string
    {
        $inConfig = $this->stringify($inConfig);

        return match (true) {
            $inSsm === null => self::MISSING_IN_SSM,
            $inConfig === null => self::MISSING_IN_CONFIG,
            $inSsm === $inConfig => self::SAME,
            default => self::CHANGED,
        };
    }

    /** Config values may be typed (env() casts "true" to a bool); parameters are always strings. */
    private function stringify(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            default => json_encode($value, JSON_THROW_ON_ERROR),
        };
    }
}
