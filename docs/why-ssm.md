# Why SSM Parameter Store instead of `.env`

The long version is in [laravel-fleet's docs](https://github.com/jpaylaga/laravel-fleet/blob/main/docs/why-ssm.md). In short:

**Benefits**
- **One source of truth:** a value changes once with `put-parameter`, not on every host.
- **Secrets off disk:** SecureString values are KMS-encrypted and only ever exist in the config cache on the host.
- **Per-tenant access:** an instance profile reads its own path and nothing else.
- **Audit trail and history:** CloudTrail records reads and writes; every parameter keeps its versions.
- **Clean bootstrap:** a new host needs an instance profile and four lines of `.env`.

**Trade-offs**
- **Reads are slow and rate-limited**, so config must be cached and the cache rebuilt carefully. That is what this package is for.
- **Not live:** changes apply at the next cache rebuild.
- **Two paths:** local development still uses `.env`, which `ssm:pull` fills.
- **Deploy-time dependency:** if the SSM API is down or throttling, the cache cannot be rebuilt.
