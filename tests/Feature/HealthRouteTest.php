<?php

it('is off by default', function () {
    $this->get('/ssm/health')->assertNotFound();
});

it('reports the mapped keys that are loaded and never a value', function () {
    $this->ssm->hasParameters(['APP_KEY' => 'base64:top-secret', 'DB_PASSWORD' => 'hunter2']);

    $this->reboot(['enabled' => true, 'health' => ['enabled' => true, 'path' => '/ssm/health', 'middleware' => []]]);

    $response = $this->get('/ssm/health')->assertStatus(503)->assertExactJson([
        'loaded' => false,
        'source' => 'ssm',
        'keys' => ['app.key', 'database.connections.mysql.password'],
        'missing' => ['database.redis.default.password', 'database.redis.cache.password'],
    ]);

    expect($response->getContent())->not->toContain('top-secret')->not->toContain('hunter2');
});

it('answers 200 only when every mapped key is present', function () {
    $this->ssm->hasParameters(['APP_KEY' => 'k', 'DB_PASSWORD' => 'p', 'REDIS_PASSWORD' => 'r']);

    $this->reboot(['enabled' => true, 'health' => ['enabled' => true, 'path' => '/internal/config-health', 'middleware' => []]]);

    $this->get('/internal/config-health')->assertOk()->assertJson(['loaded' => true, 'source' => 'ssm', 'missing' => []]);
});

it('answers 503 when a mapped key is missing', function () {
    $this->ssm->hasParameters(['APP_KEY' => 'k']);

    $this->reboot(['enabled' => true, 'health' => ['enabled' => true, 'path' => '/ssm/health', 'middleware' => []]]);

    $this->get('/ssm/health')->assertStatus(503)->assertJson(['loaded' => false]);
});

it('reports the cache as the source when the config is cached', function () {
    $this->writeCachedConfig([
        'ssm.health.enabled' => true,
        'app.key' => 'k',
        'database.connections.mysql.password' => 'p',
        'database.redis.default.password' => 'r',
        'database.redis.cache.password' => 'r',
    ]);

    $this->reboot();

    $this->get('/ssm/health')->assertOk()->assertJson(['loaded' => true, 'source' => 'cache']);
});
