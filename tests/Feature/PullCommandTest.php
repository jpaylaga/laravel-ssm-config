<?php

beforeEach(function () {
    $this->envFile = $this->workDir.'/.env';
});

it('writes the mapped parameters to a new .env, quoting what needs it', function () {
    $this->ssm->hasParameters([
        'APP_KEY' => 'base64:abc+/=',
        'DB_PASSWORD' => 'p@ss word "quoted" $notavar',
        'UNMAPPED' => 'left out',
    ]);

    $this->artisan('ssm:pull', ['--path' => $this->envFile])
        ->expectsOutput('Fetched 3 parameters from /app/tenant-a.')
        ->expectsOutput("Wrote 2 keys to {$this->envFile}: APP_KEY, DB_PASSWORD")
        ->assertExitCode(0);

    expect(file_get_contents($this->envFile))->toBe(
        'APP_KEY="base64:abc+/="'.PHP_EOL.
        'DB_PASSWORD="p@ss word \\"quoted\\" \\$notavar"'.PHP_EOL
    );

    $parsed = Dotenv\Dotenv::parse(file_get_contents($this->envFile));

    expect($parsed)->toBe(['APP_KEY' => 'base64:abc+/=', 'DB_PASSWORD' => 'p@ss word "quoted" $notavar']);
});

it('asks before touching an existing file and writes nothing on no', function () {
    file_put_contents($this->envFile, "APP_NAME=Local\nDB_PASSWORD=old\n");
    $this->ssm->hasParameters(['DB_PASSWORD' => 'new']);

    $this->artisan('ssm:pull', ['--path' => $this->envFile])
        ->expectsConfirmation("Update the mapped keys in {$this->envFile}?", 'no')
        ->expectsOutput('Nothing written.')
        ->assertExitCode(1);

    expect(file_get_contents($this->envFile))->toBe("APP_NAME=Local\nDB_PASSWORD=old\n");
});

it('updates mapped keys in place on yes and keeps every other line', function () {
    file_put_contents($this->envFile, "# local\nAPP_NAME=Local\nexport DB_PASSWORD=old\nAPP_DEBUG=true\n");
    $this->ssm->hasParameters(['DB_PASSWORD' => 'new', 'REDIS_PASSWORD' => 'r']);

    $this->artisan('ssm:pull', ['--path' => $this->envFile])
        ->expectsConfirmation("Update the mapped keys in {$this->envFile}?", 'yes')
        ->assertExitCode(0);

    expect(file_get_contents($this->envFile))->toBe(
        "# local\nAPP_NAME=Local\nDB_PASSWORD=new\nAPP_DEBUG=true\nREDIS_PASSWORD=r\n"
    );
});

it('skips the question with --force', function () {
    file_put_contents($this->envFile, "DB_PASSWORD=old\n");
    $this->ssm->hasParameters(['DB_PASSWORD' => 'new']);

    $this->artisan('ssm:pull', ['--path' => $this->envFile, '--force' => true])->assertExitCode(0);

    expect(file_get_contents($this->envFile))->toBe("DB_PASSWORD=new\n");
});

it('defaults to .env in the project root', function () {
    $this->ssm->hasParameters(['DB_PASSWORD' => 'new']);
    $this->app->setBasePath($this->workDir);

    $this->artisan('ssm:pull')->assertExitCode(0);

    expect(file_get_contents($this->workDir.'/.env'))->toBe("DB_PASSWORD=new\n");
});
