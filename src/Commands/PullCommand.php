<?php

namespace Jpaylaga\SsmConfig\Commands;

use Illuminate\Console\Command;
use Jpaylaga\SsmConfig\Steps\FetchParameters;
use Jpaylaga\SsmConfig\Steps\WriteDotEnv;
use Jpaylaga\SsmConfig\Support\ParameterPath;

class PullCommand extends Command
{
    protected $signature = 'ssm:pull
        {--path=.env : The .env file to write, relative to the project root}
        {--force : Overwrite an existing file without asking}';

    protected $description = 'Write the mapped parameters from SSM Parameter Store into a .env file';

    public function handle(
        FetchParameters $fetchParameters,
        WriteDotEnv $writeDotEnv,
        ParameterPath $path,
    ): int {
        $file = $this->resolve($this->option('path'));

        if (file_exists($file) && ! $this->option('force') && ! $this->confirm("Update the mapped keys in {$file}?")) {
            $this->line('Nothing written.');

            return self::FAILURE;
        }

        $parameters = $fetchParameters();
        $this->line(sprintf('Fetched %d parameters from %s.', count($parameters), $path));

        $keys = $writeDotEnv($parameters, $file);
        $this->info(sprintf('Wrote %d keys to %s: %s', count($keys), $file, implode(', ', $keys)));

        return self::SUCCESS;
    }

    private function resolve(mixed $path): string
    {
        $path = is_string($path) && $path !== '' ? $path : '.env';

        return str_starts_with($path, DIRECTORY_SEPARATOR) ? $path : $this->laravel->basePath($path);
    }
}
