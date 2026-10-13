<?php

namespace Jpaylaga\SsmConfig\Commands;

use Illuminate\Console\Command;
use Jpaylaga\SsmConfig\Steps\ClearCachedConfig;
use Jpaylaga\SsmConfig\Steps\FetchParameters;
use Jpaylaga\SsmConfig\Steps\MapParametersToConfig;
use Jpaylaga\SsmConfig\Steps\WriteConfigCache;
use Jpaylaga\SsmConfig\Support\ParameterMap;
use Jpaylaga\SsmConfig\Support\ParameterPath;

class CacheCommand extends Command
{
    protected $signature = 'ssm:cache';

    protected $description = 'Rebuild the config cache with the values from SSM Parameter Store';

    public function handle(
        ClearCachedConfig $clearCachedConfig,
        FetchParameters $fetchParameters,
        MapParametersToConfig $mapParametersToConfig,
        WriteConfigCache $writeConfigCache,
        ParameterPath $path,
        ParameterMap $map,
    ): int {
        if ($clearCachedConfig()) {
            $this->line('Removed the existing bootstrap/cache/config.php.');
        }

        $parameters = $fetchParameters();
        $this->line(sprintf('Fetched %d parameters from %s.', count($parameters), $path));

        $values = $mapParametersToConfig($parameters);
        $missing = array_diff($map->parameters(), array_keys($parameters));

        if ($missing !== []) {
            $this->warn('Mapped parameters missing in SSM: '.implode(', ', $missing));
        }

        $written = $writeConfigCache($values);
        $this->info(sprintf('Configuration cached with %d values from SSM in %s.', count($values), $written));

        return self::SUCCESS;
    }
}
