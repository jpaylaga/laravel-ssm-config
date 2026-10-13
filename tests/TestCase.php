<?php

namespace Jpaylaga\SsmConfig\Tests;

use Aws\Ssm\SsmClient;
use Illuminate\Support\Arr;
use Jpaylaga\SsmConfig\SsmConfigServiceProvider;
use Jpaylaga\SsmConfig\Support\FreshConfiguration;
use Jpaylaga\SsmConfig\Tests\Fakes\FakeSsm;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected FakeSsm $ssm;

    /** A scratch directory holding this test's config cache and .env files. */
    protected string $workDir;

    /** @var array<string, mixed> ssm.* overrides applied before the providers register */
    protected array $ssmConfig = [];

    protected function setUp(): void
    {
        $this->ssm = new FakeSsm;
        $this->workDir = sys_get_temp_dir().'/laravel-ssm-config-'.uniqid();
        mkdir($this->workDir);

        // Laravel resolves the cached config path from this variable, so tests can
        // create and inspect a cache without touching the Testbench skeleton.
        $_SERVER['APP_CONFIG_CACHE'] = $this->cachePath();

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($_SERVER['APP_CONFIG_CACHE']);

        foreach (glob($this->workDir.'/{,.}*', GLOB_BRACE) ?: [] as $file) {
            is_file($file) && unlink($file);
        }

        rmdir($this->workDir);
    }

    protected function getPackageProviders($app): array
    {
        return [SsmConfigServiceProvider::class];
    }

    protected function overrideApplicationBindings($app): array
    {
        return [SsmClient::class => fn () => $this->ssm->client()];
    }

    protected function resolveApplicationConfiguration($app): void
    {
        parent::resolveApplicationConfiguration($app);

        // Testbench always loads the config files; Laravel would load the cache instead.
        // Mirror Laravel here, so a cached boot starts from the cache alone, ssm.* included.
        if ($app->configurationIsCached()) {
            $app['config']->set(require $this->cachePath());

            return;
        }

        $app['config']->set('ssm', array_replace([
            'enabled' => false,
            'region' => 'us-east-1',
            'path' => '/app/tenant-a',
            'with_decryption' => true,
            'map' => [
                'APP_KEY' => 'app.key',
                'DB_PASSWORD' => 'database.connections.mysql.password',
                'REDIS_PASSWORD' => ['database.redis.default.password', 'database.redis.cache.password'],
            ],
        ], $this->ssmConfig));
    }

    /**
     * Boot the application again with these ssm.* settings (ignored when a cache
     * exists), the way a host starts: the provider's boot loader meets whatever
     * cache is on disk.
     *
     * @param  array<string, mixed>  $ssmConfig
     */
    protected function reboot(array $ssmConfig = []): void
    {
        $this->ssmConfig = $ssmConfig;
        $this->refreshApplication();
    }

    protected function cachePath(): string
    {
        return $this->workDir.'/config.php';
    }

    /**
     * Write a config cache the way config:cache would, from the running config plus
     * the given overrides, so the next boot starts from it.
     *
     * @param  array<string, mixed>  $overrides  dot keys => values
     */
    protected function writeCachedConfig(array $overrides = []): void
    {
        $config = $this->app['config']->all();

        foreach ($overrides as $key => $value) {
            Arr::set($config, $key, $value);
        }

        file_put_contents($this->cachePath(), '<?php return '.var_export($config, true).';'.PHP_EOL);
    }

    /**
     * Stand in for booting a second application: the "fresh" configuration is the
     * running one without any mapped value, plus a marker to prove it was used.
     */
    protected function fakeFreshConfiguration(): void
    {
        $this->app->instance(FreshConfiguration::class, new class($this->app, $this->cachePath()) extends FreshConfiguration
        {
            public bool $staleCacheExistedWhenBuilt = false;

            public function __construct(private $application, private string $cachePath)
            {
                parent::__construct($application);
            }

            public function __invoke(): array
            {
                $this->staleCacheExistedWhenBuilt = file_exists($this->cachePath);

                $config = $this->application['config']->all();
                Arr::set($config, 'app.name', 'fresh');
                Arr::set($config, 'database.connections.mysql.password', null);
                Arr::set($config, 'app.key', null);

                return $config;
            }
        });
    }
}
