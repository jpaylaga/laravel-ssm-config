# laravel-ssm-config

Laravel config from **AWS SSM Parameter Store**: fetched at boot on hosts without a config cache, and an `ssm:cache` command that never builds the new cache on top of a stale one.

## Background

**Why it exists**
- I maintain a multi-tenant Laravel platform where each tenant runs on its own EC2 host, with its secrets in Parameter Store under its own path.
- Before that, config lived in hand-edited `.env` files, one per host. They drifted apart, ended up in AMIs and snapshots, and nobody could say for sure which host had which value.
- Loading from Parameter Store at boot fixed the drift, but a boot loader has to skip the fetch whenever a config cache exists, or every request would call the API.
- That made the cache rebuild fragile: a `config:cache` run while the old cache was still in place could produce a config without a single SSM value, and the host only showed it on the next request.
- This package turns those lessons into one place: a boot loader with the skip built in, a cache command that removes the old cache before it fetches, and a diff that reports drift without printing values.

**How it is used**
- Each host carries a minimal `.env` (`APP_ENV`, `SSM_ENABLED`, `SSM_PATH`, `AWS_DEFAULT_REGION`). Everything else lives under `/app/tenant-a/...` in Parameter Store.
- A deploy ends with `php artisan ssm:cache`, so the host runs from a config cache that includes the SSM values.
- `php artisan ssm:diff` answers "does this host still match Parameter Store?" with statuses only, so its output is safe to paste into a ticket.
- `php artisan ssm:pull` fills a developer's local `.env` from the same path.

**Limitations**
- Built for one setup (below): EC2 hosts with instance profiles, one Parameter Store path per host, config cached per host.
- Not for Lambda or containers as is. The fetch works wherever the SDK finds credentials, but the per-host cache is the design; those platforms want the fetch at build time or a different cache strategy.
- One path per application, read with `GetParametersByPath`. No cross-path references, parameter versions or labels.
- Values go into config keys, never into `env()`. A config file that reads `env('X')` still does; the map decides which keys Parameter Store overrides.
- Not live: a change in Parameter Store reaches a host at its next `ssm:cache`.

## The setup it targets

- **Compute:** EC2 on Ubuntu, one host per tenant
- **App:** Laravel 9 on PHP 8.1, nginx + PHP-FPM
- **Background work:** Laravel queue workers and scheduler under Supervisor
- **Database:** Amazon Aurora MySQL (Serverless v2)
- **Cache and queues:** Redis, on-host and Amazon ElastiCache
- **Config and secrets:** AWS SSM Parameter Store, loaded at boot
- **Realtime:** Soketi (self-hosted, Pusher-compatible WebSockets)
- **DNS:** Amazon Route 53
- **Monitoring:** Prometheus + Grafana
- **Access:** AWS IAM Identity Center

## Why SSM Parameter Store instead of `.env`

The long version is in [laravel-fleet's README](https://github.com/jpaylaga/laravel-fleet#why-ssm-parameter-store-instead-of-env). In short:

**Benefits**
- **One source of truth:** a value changes once with `put-parameter`, not on every host.
- **Secrets off disk:** SecureString values are KMS-encrypted and only ever exist in the config cache on the host.
- **Per-tenant access:** an instance profile reads its own path and nothing else.
- **Audit trail and history:** CloudTrail records reads and writes; every parameter keeps its versions.
- **Clean bootstrap:** a new host needs an instance profile and four lines of `.env`.

**Trade-offs**
- **Reads are slow and rate-limited**, so config must be cached and the cache rebuilt carefully. That is what this package is for.
- **Not live:** changes apply at the next cache rebuild.
- **Two paths:** local development still uses `.env`, which `ssm:pull` fills.
- **Deploy-time dependency:** if the SSM API is down or throttling, the cache cannot be rebuilt.

## How it works

**At boot** (in the service provider's `register()`, before the application's own providers register)
- Config cached: nothing happens. The cache is the runtime source and no request ever calls SSM.
- Config not cached and `ssm.enabled` off: nothing happens. Config comes from the files and `.env`, as in local development.
- Otherwise: `FetchParameters` → `MapParametersToConfig` → `ApplyConfig`. One recursive, decrypted, paginated `GetParametersByPath` call per boot, and the mapped values are set on the config repository.
- Which of the three happened is bound in the container as `ConfigSource` (`cache`, `disabled`, `ssm`), for your own checks.

**`php artisan ssm:cache`**
1. `ClearCachedConfig` removes `bootstrap/cache/config.php`, and fails loudly if it cannot.
2. `FetchParameters` and `MapParametersToConfig` fetch once and warn about mapped parameters that are missing in SSM.
3. `WriteConfigCache` boots a second application from `bootstrap/app.php` (exactly what `config:cache` does), with the boot fetch switched off, sets the fetched values on that fresh config, writes the file in `config:cache`'s format and loads it back as a check.

- The running process's own config is never the base of the new cache, so it does not matter whether that process started from an old cache.
- Values are applied to config keys with dot notation, so `DB_PASSWORD` can target `database.connections.mysql.password` and a parameter can fill several keys.

## Getting started

**1. Grant each host read access to its own path**
- Attach [`docs/iam-policy.json`](docs/iam-policy.json) to the host's instance profile, with the path changed to the host's own.
- Keep the `kms:Decrypt` statement only if the parameters use a customer managed key; the default `aws/ssm` key needs nothing extra.

**2. Put the parameters**

```bash
aws ssm put-parameter --name /app/tenant-a/DB_PASSWORD --type SecureString --value '...'
aws ssm put-parameter --name /app/tenant-a/APP_KEY --type SecureString --value 'base64:...'
```

**3. Install and publish the config**

```bash
composer require jpaylaga/laravel-ssm-config      # PHP 8.1+, Laravel 9 to 12
php artisan vendor:publish --tag=ssm-config       # writes config/ssm.php
```

**4. Point each host at its path**

```dotenv
APP_ENV=production
SSM_ENABLED=true
SSM_PATH=/app/tenant-a
AWS_DEFAULT_REGION=us-west-2
```

- Then `php artisan ssm:diff` to see what would change, and `php artisan ssm:cache` to apply it.

## Config

`config/ssm.php`, after `vendor:publish`:

```php
return [
    // Fetch at boot when no config cache exists. Off for local development.
    'enabled' => (bool) env('SSM_ENABLED', false),

    'region' => env('SSM_REGION', env('AWS_DEFAULT_REGION')),

    // Every parameter under this path is fetched (recursively).
    'path' => env('SSM_PATH', '/app/tenant-a'),

    // Decrypt SecureString parameters (needs kms:Decrypt on their key).
    'with_decryption' => true,

    // Parameter name (relative to the path) => config key(s) it populates.
    // Parameters under the path that are not listed here are ignored.
    'map' => [
        'APP_KEY' => 'app.key',
        'DB_HOST' => 'database.connections.mysql.host',
        'DB_DATABASE' => 'database.connections.mysql.database',
        'DB_USERNAME' => 'database.connections.mysql.username',
        'DB_PASSWORD' => 'database.connections.mysql.password',
        'REDIS_PASSWORD' => ['database.redis.default.password', 'database.redis.cache.password'],
        'MAIL_PASSWORD' => 'mail.mailers.smtp.password',
    ],
];
```

| Key | Default | Meaning |
| --- | --- | --- |
| `enabled` | `SSM_ENABLED`, `false` | Fetch at boot when the config is not cached. The commands run regardless |
| `region` | `SSM_REGION` or `AWS_DEFAULT_REGION` | Region of the Parameter Store; credentials come from the SDK's default chain |
| `path` | `SSM_PATH`, `/app/tenant-a` | Absolute path fetched recursively; parameter names in `map` are relative to it |
| `with_decryption` | `true` | Decrypt SecureString values |
| `map` | a starter set | Parameter name => config key, or a list of keys. Nested names work too (`mail/PASSWORD`) |

- Bind your own `Aws\Ssm\SsmClient` in the container to replace the one the package builds (an endpoint override, a custom credential provider).

## Commands

```bash
php artisan ssm:cache                        # remove the old cache, fetch, write the new cache
php artisan ssm:diff                         # status per mapped key, no values; exit 1 on drift
php artisan ssm:pull [--path=.env] [--force] # write the mapped parameters into a .env
```

- `ssm:diff` statuses: `same`, `changed`, `missing in SSM`, `missing in config`.
- `ssm:pull` replaces the mapped keys in place and keeps every other line. It asks before touching an existing file unless `--force`.

## Use case: rotating a database password

**Setup:** the config above, `DB_PASSWORD` mapped to `database.connections.mysql.password`, one host for `tenant-a`.

**1. Rotate the parameter**

```bash
aws ssm put-parameter --name /app/tenant-a/DB_PASSWORD --type SecureString --value '...' --overwrite
```

**2. See what the host would change**

```console
$ php artisan ssm:diff
Comparing 4 parameters under /app/tenant-a with the running config.
+----------------+-------------------------------------+----------------+
| Parameter      | Config key                          | Status         |
+----------------+-------------------------------------+----------------+
| APP_KEY        | app.key                             | same           |
| DB_PASSWORD    | database.connections.mysql.password | changed        |
| REDIS_PASSWORD | database.redis.default.password     | same           |
| REDIS_PASSWORD | database.redis.cache.password       | same           |
| MAIL_PASSWORD  | mail.mailers.smtp.password          | missing in SSM |
+----------------+-------------------------------------+----------------+
2 of 5 mapped keys differ. Run `php artisan ssm:cache` to apply SSM.
```

- Exit code 1, so a cron or CI job can alert on drift.
- `MAIL_PASSWORD` is mapped but was never put in Parameter Store: worth fixing, and visible without printing anything sensitive.

**3. Apply it**

```console
$ php artisan ssm:cache
Removed the existing bootstrap/cache/config.php.
Fetched 4 parameters from /app/tenant-a.
Mapped parameters missing in SSM: MAIL_PASSWORD
Configuration cached with 4 values from SSM in /var/www/app/bootstrap/cache/config.php.
```

- Followed by the usual `php artisan queue:restart` and a PHP-FPM reload, so workers and OPcache pick up the new cache.

**4. Confirm**

```console
$ php artisan ssm:diff
Comparing 4 parameters under /app/tenant-a with the running config.
...
| DB_PASSWORD    | database.connections.mysql.password | same           |
...
```

**5. A developer needs the new value locally**

```console
$ php artisan ssm:pull
Fetched 4 parameters from /app/tenant-a.
Wrote 3 keys to /home/dev/app/.env: APP_KEY, DB_PASSWORD, REDIS_PASSWORD
```

## Works with laravel-fleet

[laravel-fleet](https://github.com/jpaylaga/laravel-fleet) deploys to the same hosts over SSM Run Command. The two fit together:

- Its `caches` stage already deletes the old cache before `config:cache`. With this package installed, pass a `caches` stage that runs `php artisan ssm:cache` instead and the rebuild is also a fetch.
- `fleet exec -- php artisan ssm:diff` reports drift across every host at once.

## Project layout

- One class per file, named after it.
- Each command's `handle()` reads as the orchestration, a list of steps and nothing else: `ssm:cache` is `ClearCachedConfig` → `FetchParameters` → `MapParametersToConfig` → `WriteConfigCache`.
- Steps shared by several entry points live once: `FetchParameters` serves the boot loader and all three commands.

```
src/
  SsmConfigServiceProvider.php   boot loader and commands
  Commands/                      CacheCommand, DiffCommand, PullCommand
  Steps/                         ClearCachedConfig, FetchParameters, MapParametersToConfig, ApplyConfig,
                                 WriteConfigCache, DiffParameters, WriteDotEnv
  Support/                       SsmClientFactory, ParameterPath, ParameterMap, FreshConfiguration,
                                 DotEnvFile, ConfigSource
config/ssm.php                   publishable config
docs/iam-policy.json             instance profile policy, scoped to one path
tests/                           Pest + Testbench; SSM faked with the SDK's MockHandler, no network
```

## Development

```bash
composer install
composer check      # pint --test, phpstan (larastan, level 8), pest
```

- The tests cover a paginated fetch, the cached-boot skip, the stale-cache removal in `ssm:cache`, every `ssm:diff` status and exit code, and `ssm:pull` with and without `--force`.

## License

MIT
