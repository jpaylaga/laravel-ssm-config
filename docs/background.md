# Background

## Background

**Why it exists**
- I maintain a multi-tenant Laravel platform where each tenant runs on its own EC2 host, with its secrets in Parameter Store under its own path.
- Before that, config lived in hand-edited `.env` files, one per host. They drifted apart, ended up in AMIs and snapshots, and nobody could say for sure which host had which value.
- Loading from Parameter Store at boot fixed the drift, but a boot loader has to skip the fetch whenever a config cache exists, or every request would call the API.
- That made the cache rebuild fragile: a `config:cache` run while the old cache was still in place could produce a config without a single SSM value, and the host only showed it on the next request.
- This package turns those lessons into one place: a boot loader with the skip built in, a cache command that removes the old cache before it fetches, and a diff that reports drift without printing values.

**How it is used**
- Each host carries a minimal `.env` (`APP_ENV`, `SSM_ENABLED`, `SSM_PATH`, `AWS_DEFAULT_REGION`). Everything else lives under `/app/tenant-a/...` in Parameter Store.
- A deploy ends with `php artisan ssm:cache`, so the host runs from a config cache that includes the SSM values.
- `php artisan ssm:diff` answers "does this host still match Parameter Store?" with statuses only, so its output is safe to paste into a ticket.
- `php artisan ssm:pull` fills a developer's local `.env` from the same path.
- An opt-in health route tells a post-deploy smoke check whether every mapped key is loaded.

**Limitations**
- Built for one setup (below): EC2 hosts with instance profiles, one Parameter Store path per host, config cached per host.
- Not for Lambda or containers as is. The fetch works wherever the SDK finds credentials, but the per-host cache is the design; those platforms want the fetch at build time or a different cache strategy.
- One path per application, read with `GetParametersByPath`. No cross-path references, parameter versions or labels.
- Values go into config keys, never into `env()`. A config file that reads `env('X')` still does; the map decides which keys Parameter Store overrides.
- Not live: a change in Parameter Store reaches a host at its next `ssm:cache`.

## The setup it targets

- **Compute:** EC2 on Ubuntu, one host per tenant
- **App:** Laravel 9 on PHP 8.1, nginx + PHP-FPM
- **Background work:** Laravel queue workers and scheduler under Supervisor
- **Database:** Amazon Aurora MySQL (Serverless v2)
- **Cache and queues:** Redis, on-host and Amazon ElastiCache
- **Config and secrets:** AWS SSM Parameter Store, loaded at boot
- **Realtime:** Soketi (self-hosted, Pusher-compatible WebSockets)
- **DNS:** Amazon Route 53
- **Monitoring:** Prometheus + Grafana
- **Access:** AWS IAM Identity Center
