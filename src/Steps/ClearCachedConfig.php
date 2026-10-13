<?php

namespace Jpaylaga\SsmConfig\Steps;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;

/**
 * Removes bootstrap/cache/config.php before a rebuild. With a cached config in
 * place the boot fetch is skipped, so a cache built on top of a stale one would
 * carry no SSM values. Returns true when a stale cache was removed.
 */
final class ClearCachedConfig
{
    public function __construct(
        private Application $app,
        private Filesystem $files,
    ) {}

    public function __invoke(): bool
    {
        $path = $this->app->getCachedConfigPath();

        if (! $this->files->exists($path)) {
            return false;
        }

        $this->files->delete($path);
        clearstatcache(true, $path);

        if (is_file($path)) {
            throw new RuntimeException("Could not remove the cached config at {$path}; check its permissions.");
        }

        return true;
    }
}
