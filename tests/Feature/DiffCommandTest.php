<?php

use Illuminate\Support\Facades\Artisan;

it('reports a status per mapped key and fails on drift, without printing values', function () {
    config()->set('app.key', 'base64:same-key');
    config()->set('database.connections.mysql.password', 'old-secret');
    config()->set('database.redis.default.password', null);
    config()->set('database.redis.cache.password', 'orphaned-in-config');

    $this->ssm->hasParameters([
        'APP_KEY' => 'base64:same-key',
        'DB_PASSWORD' => 'new-secret',
        'MAIL_PASSWORD' => 'not-in-the-map',
    ]);

    $this->artisan('ssm:diff')
        ->expectsOutput('Comparing 3 parameters under /app/tenant-a with the running config.')
        ->expectsTable(['Parameter', 'Config key', 'Status'], [
            ['APP_KEY', 'app.key', 'same'],
            ['DB_PASSWORD', 'database.connections.mysql.password', 'changed'],
            ['REDIS_PASSWORD', 'database.redis.default.password', 'missing in SSM'],
            ['REDIS_PASSWORD', 'database.redis.cache.password', 'missing in SSM'],
        ])
        ->expectsOutput('3 of 4 mapped keys differ. Run `php artisan ssm:cache` to apply SSM.')
        ->assertExitCode(1);

    $output = Artisan::output();

    expect($output)->not->toContain('same-key')
        ->and($output)->not->toContain('old-secret')
        ->and($output)->not->toContain('new-secret');
});

it('reports a parameter the config does not have yet', function () {
    config()->set('app.key', 'base64:same-key');
    config()->set('database.connections.mysql.password', null);

    $this->ssm->hasParameters(['APP_KEY' => 'base64:same-key', 'DB_PASSWORD' => 'secret', 'REDIS_PASSWORD' => 'r']);
    config()->set('database.redis.default.password', 'r');
    config()->set('database.redis.cache.password', 'r');

    $this->artisan('ssm:diff')
        ->expectsTable(['Parameter', 'Config key', 'Status'], [
            ['APP_KEY', 'app.key', 'same'],
            ['DB_PASSWORD', 'database.connections.mysql.password', 'missing in config'],
            ['REDIS_PASSWORD', 'database.redis.default.password', 'same'],
            ['REDIS_PASSWORD', 'database.redis.cache.password', 'same'],
        ])
        ->assertExitCode(1);
});

it('succeeds when everything matches, comparing typed config values as strings', function () {
    $this->reboot(['map' => ['APP_DEBUG' => 'app.debug', 'DB_PORT' => 'database.connections.mysql.port']]);
    config()->set('app.debug', true);
    config()->set('database.connections.mysql.port', 3306);

    $this->ssm->hasParameters(['APP_DEBUG' => 'true', 'DB_PORT' => '3306']);

    $this->artisan('ssm:diff')
        ->expectsOutput('All 2 mapped keys match.')
        ->assertExitCode(0);
});
