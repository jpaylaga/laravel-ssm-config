<?php

return [

    /*
     * Fetch parameters at boot when no config cache exists. Leave it off for
     * local development, where .env is the source of config, and turn it on
     * for hosts whose config comes from Parameter Store.
     */
    'enabled' => (bool) env('SSM_ENABLED', false),

    /*
     * Region of the Parameter Store the host reads from.
     */
    'region' => env('SSM_REGION', env('AWS_DEFAULT_REGION')),

    /*
     * Every parameter under this path is fetched (recursively). Each host or
     * tenant gets its own path, and its instance profile can read only that.
     */
    'path' => env('SSM_PATH', '/app/tenant-a'),

    /*
     * Decrypt SecureString parameters. Needs kms:Decrypt on the key they use.
     */
    'with_decryption' => true,

    /*
     * Parameter name (relative to the path) => config key it populates.
     * A parameter may populate several keys by mapping it to a list.
     * Parameters under the path that are not listed here are ignored.
     */
    'map' => [
        'APP_KEY' => 'app.key',
        'DB_HOST' => 'database.connections.mysql.host',
        'DB_DATABASE' => 'database.connections.mysql.database',
        'DB_USERNAME' => 'database.connections.mysql.username',
        'DB_PASSWORD' => 'database.connections.mysql.password',
        'REDIS_PASSWORD' => ['database.redis.default.password', 'database.redis.cache.password'],
        'MAIL_PASSWORD' => 'mail.mailers.smtp.password',
    ],

    /*
     * Optional JSON route that reports whether the mapped keys are loaded,
     * without ever exposing a value. Off by default.
     */
    'health' => [
        'enabled' => (bool) env('SSM_HEALTH_ENABLED', false),
        'path' => '/ssm/health',
        'middleware' => [],
    ],

];
