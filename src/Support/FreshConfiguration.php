<?php

namespace Jpaylaga\SsmConfig\Support;

use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;

/**
 * Builds the configuration a brand-new application would start with, the same
 * way Laravel's own config:cache does: by booting a second application from
 * bootstrap/app.php. The running process may itself have started from a cached
 * config, so its own repository is never used as the base of a new cache.
 *
 * The second application skips the boot fetch (see SKIP_BOOT_FETCH); the caller
 * merges in the parameters it already fetched.
 */
class FreshConfiguration
{
    /** Container binding whose presence tells the service provider not to fetch at boot. */
    public const SKIP_BOOT_FETCH = 'ssm-config.skip-boot-fetch';

    public function __construct(private Application $app) {}

    /** @return array<string, mixed> */
    public function __invoke(): array
    {
        try {
            /** @var Application $fresh */
            $fresh = require $this->app->bootstrapPath().'/app.php';

            $fresh->useStoragePath($this->app->storagePath());
            $fresh->instance(self::SKIP_BOOT_FETCH, true);
            $fresh->make(Kernel::class)->bootstrap();

            /** @var array<string, mixed> $config */
            $config = $fresh['config']->all();

            return $config;
        } finally {
            // Booting the second application made it the global container and facade root.
            Container::setInstance($this->app);
            Facade::clearResolvedInstances();
            Facade::setFacadeApplication($this->app);
        }
    }
}
