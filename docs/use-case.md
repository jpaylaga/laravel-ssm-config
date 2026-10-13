# Use case: rotating a database password

**Setup:** the [example config](configuration.md), `DB_PASSWORD` mapped to `database.connections.mysql.password`, one host for `tenant-a`.

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
