# Development

## Project layout

- One class per file, named after it.
- Each command's `handle()` reads as the orchestration, a list of steps and nothing else: `ssm:cache` is `ClearCachedConfig` → `FetchParameters` → `MapParametersToConfig` → `WriteConfigCache`.
- Steps shared by several entry points live once: `FetchParameters` serves the boot loader and all three commands.

```
src/
  SsmConfigServiceProvider.php   boot loader, commands, health route
  Commands/                      CacheCommand, DiffCommand, PullCommand
  Steps/                         ClearCachedConfig, FetchParameters, MapParametersToConfig, ApplyConfig,
                                 WriteConfigCache, DiffParameters, WriteDotEnv
  Support/                       SsmClientFactory, ParameterPath, ParameterMap, FreshConfiguration,
                                 DotEnvFile, ConfigSource
  Http/                          HealthController
config/ssm.php                   publishable config
docs/iam-policy.json             instance profile policy, scoped to one path
tests/                           Pest + Testbench; SSM faked with the SDK's MockHandler, no network
```

## Development

```bash
composer install
composer check      # pint --test, phpstan (larastan, level 8), pest
```

- Verified end to end on Laravel 9 / PHP 8.1 (the [target setup](background.md#the-setup-it-targets), in a real application) and on Laravel 12 / PHP 8.3.
- CI runs the suite on PHP 8.1 to 8.4 against Laravel 9 to 12 (minus the pairs they do not support).
- The tests cover a paginated fetch, the cached-boot skip, the stale-cache removal in `ssm:cache`, every `ssm:diff` status and exit code, `ssm:pull` with and without `--force`, and a health route that is off by default and never leaks a value.
