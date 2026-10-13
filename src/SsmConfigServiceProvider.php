<?php

namespace Jpaylaga\SsmConfig;

use Aws\Ssm\SsmClient;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Jpaylaga\SsmConfig\Commands\CacheCommand;
use Jpaylaga\SsmConfig\Commands\DiffCommand;
use Jpaylaga\SsmConfig\Commands\PullCommand;
use Jpaylaga\SsmConfig\Steps\ApplyConfig;
use Jpaylaga\SsmConfig\Steps\FetchParameters;
use Jpaylaga\SsmConfig\Steps\MapParametersToConfig;
use Jpaylaga\SsmConfig\Support\ConfigSource;
use Jpaylaga\SsmConfig\Support\FreshConfiguration;
use Jpaylaga\SsmConfig\Support\ParameterMap;
use Jpaylaga\SsmConfig\Support\ParameterPath;
use Jpaylaga\SsmConfig\Support\SsmClientFactory;

class SsmConfigServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ssm.php', 'ssm');

        // singletonIf, so an application (or a test) can bind its own client first.
        $this->app->singletonIf(SsmClient::class, fn (Application $app) => (new SsmClientFactory)($this->config($app)->get('ssm.region')));
        $this->app->singleton(ParameterPath::class, fn (Application $app) => new ParameterPath($this->config($app)->get('ssm.path')));
        $this->app->singleton(ParameterMap::class, fn (Application $app) => new ParameterMap($this->config($app)->get('ssm.map', [])));

        $this->app->instance(ConfigSource::class, $this->loadAtBoot());
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/ssm.php' => $this->app->configPath('ssm.php')], 'ssm-config');

        if ($this->app->runningInConsole()) {
            $this->commands([CacheCommand::class, DiffCommand::class, PullCommand::class]);
        }
    }

    /**
     * The boot loader: fetch and apply the mapped parameters, unless the config is
     * cached (the cache is then the runtime source) or the fetch is turned off.
     */
    private function loadAtBoot(): ConfigSource
    {
        if ($this->app->configurationIsCached()) {
            return ConfigSource::Cache;
        }

        if (! $this->config($this->app)->get('ssm.enabled') || $this->app->bound(FreshConfiguration::SKIP_BOOT_FETCH)) {
            return ConfigSource::Disabled;
        }

        $parameters = $this->app->make(FetchParameters::class)();
        $values = $this->app->make(MapParametersToConfig::class)($parameters);
        $this->app->make(ApplyConfig::class)($values);

        return ConfigSource::Ssm;
    }

    private function config(Application $app): Repository
    {
        return $app->make(Repository::class);
    }
}
