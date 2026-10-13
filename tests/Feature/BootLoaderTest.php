<?php

use Jpaylaga\SsmConfig\Support\ConfigSource;

it('applies mapped parameters to the config at boot, across pages', function () {
    $this->ssm->hasParameters(
        ['APP_KEY' => 'base64:key-from-ssm', 'UNMAPPED' => 'ignored'],
        ['DB_PASSWORD' => 'db-secret-from-ssm'],
    );

    $this->reboot(['enabled' => true]);

    expect(config('app.key'))->toBe('base64:key-from-ssm')
        ->and(config('database.connections.mysql.password'))->toBe('db-secret-from-ssm')
        ->and(config('database.redis.default.password'))->toBeNull()
        ->and($this->app->make(ConfigSource::class))->toBe(ConfigSource::Ssm);

    expect($this->ssm->commands)->toHaveCount(2);
    expect($this->ssm->commands[0]['Path'])->toBe('/app/tenant-a')
        ->and($this->ssm->commands[0]['Recursive'])->toBeTrue()
        ->and($this->ssm->commands[0]['WithDecryption'])->toBeTrue()
        ->and($this->ssm->commands[0]['NextToken'] ?? null)->toBeNull()
        ->and($this->ssm->commands[1]['NextToken'])->toBe('page-1');
});

it('populates every config key mapped to one parameter', function () {
    $this->ssm->hasParameters(['REDIS_PASSWORD' => 'redis-secret']);

    $this->reboot(['enabled' => true]);

    expect(config('database.redis.default.password'))->toBe('redis-secret')
        ->and(config('database.redis.cache.password'))->toBe('redis-secret');
});

it('does not touch SSM when the config is cached', function () {
    $this->writeCachedConfig([
        'ssm.enabled' => true,
        'app.key' => 'base64:from-the-cache',
    ]);

    $this->reboot();

    expect($this->app->configurationIsCached())->toBeTrue()
        ->and(config('app.key'))->toBe('base64:from-the-cache')
        ->and($this->app->make(ConfigSource::class))->toBe(ConfigSource::Cache)
        ->and($this->ssm->commands)->toBe([]);
});

it('does not touch SSM when disabled', function () {
    $this->reboot(['enabled' => false]);

    expect($this->app->make(ConfigSource::class))->toBe(ConfigSource::Disabled)
        ->and($this->ssm->commands)->toBe([]);
});

it('rejects a relative parameter path', function () {
    $this->ssm->hasParameters([]);

    $this->reboot(['enabled' => true, 'path' => 'app/tenant-a']);
})->throws(InvalidArgumentException::class, 'ssm.path');
