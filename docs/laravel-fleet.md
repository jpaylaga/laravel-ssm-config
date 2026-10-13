# Works with laravel-fleet

[laravel-fleet](https://github.com/jpaylaga/laravel-fleet) deploys to the same hosts over SSM Run Command. The two fit together:

- Its `caches` stage already deletes the old cache before `config:cache`. With this package installed, pass a `caches` stage that runs `php artisan ssm:cache` instead and the rebuild is also a fetch.
- Its `smoke` stage can hit the health route: `"smoke": { "url": "http://127.0.0.1/ssm/health", "host": "app.example.com" }`. A `503` means a mapped key is missing, and the stage rolls the host back.
- `fleet exec -- php artisan ssm:diff` reports drift across every host at once.
