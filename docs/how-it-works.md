# How it works

**At boot** (in the service provider's `register()`, before the application's own providers register)
- Config cached: nothing happens. The cache is the runtime source and no request ever calls SSM.
- Config not cached and `ssm.enabled` off: nothing happens. Config comes from the files and `.env`, as in local development.
- Otherwise: `FetchParameters` → `MapParametersToConfig` → `ApplyConfig`. One recursive, decrypted, paginated `GetParametersByPath` call per boot, and the mapped values are set on the config repository.
- Which of the three happened is bound in the container as `ConfigSource` (`cache`, `disabled`, `ssm`), for the health route or your own checks.

**`php artisan ssm:cache`**
1. `ClearCachedConfig` removes `bootstrap/cache/config.php`, and fails loudly if it cannot.
2. `FetchParameters` and `MapParametersToConfig` fetch once and warn about mapped parameters that are missing in SSM.
3. `WriteConfigCache` boots a second application from `bootstrap/app.php` (exactly what `config:cache` does), with the boot fetch switched off, sets the fetched values on that fresh config, writes the file in `config:cache`'s format and loads it back as a check.

- The running process's own config is never the base of the new cache, so it does not matter whether that process started from an old cache.
- Values are applied to config keys with dot notation, so `DB_PASSWORD` can target `database.connections.mysql.password` and a parameter can fill several keys.
