<?php

use Illuminate\Support\Facades\Artisan;
use Jpaylaga\SsmConfig\Support\FreshConfiguration;

it('removes the stale cache before building the new one', function () {
    $this->writeCachedConfig(['database.connections.mysql.password' => 'stale-secret']);
    $this->reboot(['enabled' => true]);
    expect($this->app->configurationIsCached())->toBeTrue();

    $this->fakeFreshConfiguration();
    $this->ssm->hasParameters(['APP_KEY' => 'base64:new-key', 'DB_PASSWORD' => 'new-secret']);

    $this->artisan('ssm:cache')
        ->expectsOutput('Removed the existing bootstrap/cache/config.php.')
        ->expectsOutput('Fetched 2 parameters from /app/tenant-a.')
        ->expectsOutput('Mapped parameters missing in SSM: REDIS_PASSWORD')
        ->assertExitCode(0);

    expect($this->app->make(FreshConfiguration::class)->staleCacheExistedWhenBuilt)->toBeFalse();

    $cached = require $this->cachePath();

    expect($cached['app']['name'])->toBe('fresh')
        ->and($cached['app']['key'])->toBe('base64:new-key')
        ->and($cached['database']['connections']['mysql']['password'])->toBe('new-secret')
        ->and(file_get_contents($this->cachePath()))->not->toContain('stale-secret');
});

it('builds the cache when none exists', function () {
    $this->fakeFreshConfiguration();
    $this->ssm->hasParameters(['APP_KEY' => 'base64:new-key', 'DB_PASSWORD' => 'new-secret', 'REDIS_PASSWORD' => 'r']);

    $this->artisan('ssm:cache')
        ->doesntExpectOutput('Removed the existing bootstrap/cache/config.php.')
        ->expectsOutput('Configuration cached with 4 values from SSM in '.$this->cachePath().'.')
        ->assertExitCode(0);

    $cached = require $this->cachePath();

    expect($cached['database']['redis']['cache']['password'])->toBe('r');
});

it('never prints a value', function () {
    $this->fakeFreshConfiguration();
    $this->ssm->hasParameters(['DB_PASSWORD' => 'hunter2']);

    $this->artisan('ssm:cache')->run();

    expect(Artisan::output())->not->toContain('hunter2');
});
