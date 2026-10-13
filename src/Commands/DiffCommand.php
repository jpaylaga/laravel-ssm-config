<?php

namespace Jpaylaga\SsmConfig\Commands;

use Illuminate\Console\Command;
use Jpaylaga\SsmConfig\Steps\DiffParameters;
use Jpaylaga\SsmConfig\Steps\FetchParameters;
use Jpaylaga\SsmConfig\Support\ParameterPath;

class DiffCommand extends Command
{
    protected $signature = 'ssm:diff';

    protected $description = 'Compare SSM Parameter Store with the running config, without printing values';

    public function handle(
        FetchParameters $fetchParameters,
        DiffParameters $diffParameters,
        ParameterPath $path,
    ): int {
        $parameters = $fetchParameters();
        $this->line(sprintf('Comparing %d parameters under %s with the running config.', count($parameters), $path));

        $rows = $diffParameters($parameters);
        $this->table(['Parameter', 'Config key', 'Status'], $rows);

        $drifted = count(array_filter($rows, fn (array $row) => $row['status'] !== DiffParameters::SAME));

        if ($drifted === 0) {
            $this->info(sprintf('All %d mapped keys match.', count($rows)));

            return self::SUCCESS;
        }

        $this->warn(sprintf('%d of %d mapped keys differ. Run `php artisan ssm:cache` to apply SSM.', $drifted, count($rows)));

        return self::FAILURE;
    }
}
