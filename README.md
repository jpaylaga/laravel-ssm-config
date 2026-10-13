# laravel-ssm-config

Laravel config from **AWS SSM Parameter Store**: fetched at boot on hosts without a config cache, and an `ssm:cache` command that never builds the new cache on top of a stale one.

- Built for one setup: Laravel on EC2 with instance profiles and a config cache per host ([background](docs/background.md)).
- Works with [laravel-fleet](https://github.com/jpaylaga/laravel-fleet) for the deploy side.

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

- Every config key and default: [docs/configuration.md](docs/configuration.md).

## Commands

```bash
php artisan ssm:cache                        # remove the old cache, fetch, write the new cache
php artisan ssm:diff                         # status per mapped key, no values; exit 1 on drift
php artisan ssm:pull [--path=.env] [--force] # write the mapped parameters into a .env
```

- `ssm:diff` statuses: `same`, `changed`, `missing in SSM`, `missing in config`.
- `ssm:pull` replaces the mapped keys in place and keeps every other line. It asks before touching an existing file unless `--force`.
- Health route (`GET /ssm/health` when enabled): `{"loaded": true, "source": "cache", "keys": [...], "missing": []}` with `200`, or `503` while a mapped key is missing. Key names only, never values.

## Documentation

- [Background](docs/background.md): why it exists, limitations, the setup it targets
- [How it works](docs/how-it-works.md): the boot loader and `ssm:cache`
- [Configuration](docs/configuration.md): full `config/ssm.php` and every key
- [Use case](docs/use-case.md): rotating a database password
- [Works with laravel-fleet](docs/laravel-fleet.md)
- [Why SSM Parameter Store](docs/why-ssm.md): benefits and trade-offs versus `.env`
- [Development](docs/development.md): project layout, tests, CI

## License

MIT
