<?php

namespace Jpaylaga\SsmConfig\Steps;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Jpaylaga\SsmConfig\Support\FreshConfiguration;
use LogicException;
use Throwable;

/**
 * Writes bootstrap/cache/config.php from a fresh configuration plus the mapped
 * SSM values, in the same format Laravel's config:cache produces.
 */
final class WriteConfigCache
{
    public function __construct(
        private Application $app,
        private Filesystem $files,
        private FreshConfiguration $fresh,
    ) {}

    /**
     * @param  array<string, string>  $values  config key => value
     * @return string the path written
     */
    public function __invoke(array $values): string
    {
        $config = ($this->fresh)();

        foreach ($values as $key => $value) {
            Arr::set($config, $key, $value);
        }

        $path = $this->app->getCachedConfigPath();

        $this->files->put($path, '<?php return '.var_export($config, true).';'.PHP_EOL);

        try {
            require $path;
        } catch (Throwable $e) {
            $this->files->delete($path);

            throw new LogicException('Your configuration files are not serializable.', 0, $e);
        }

        return $path;
    }
}
