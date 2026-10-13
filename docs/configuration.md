# Configuration

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

    // JSON route reporting whether the mapped keys are loaded. Never a value.
    'health' => [
        'enabled' => (bool) env('SSM_HEALTH_ENABLED', false),
        'path' => '/ssm/health',
        'middleware' => [],
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
| `health.enabled` | `SSM_HEALTH_ENABLED`, `false` | Register the health route |
| `health.path` | `/ssm/health` | Route path; the route is named `ssm.health` |
| `health.middleware` | `[]` | Middleware for the route, e.g. an IP allowlist |

- Bind your own `Aws\Ssm\SsmClient` in the container to replace the one the package builds (an endpoint override, a custom credential provider).
