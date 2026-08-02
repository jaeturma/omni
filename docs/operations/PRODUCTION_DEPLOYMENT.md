# Production Deployment and Environment Governance

No deployment is automatic. Every release requires explicit owner approval, a verified pre-deployment backup, and a recorded operator. Examples below assume a single Ubuntu/Debian host with Nginx, PHP-FPM, MySQL, and releases under `/var/www/omni`; adapt paths without weakening the controls.

## Production baseline

- 2 CPU cores, 4 GB RAM, 40 GB encrypted storage plus separately managed off-site backup capacity; increase only from measured demand.
- Linux with NTP enabled; server, PHP, MySQL, and Laravel use `Asia/Manila` for business dates.
- PHP 8.3 or newer with `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `session`, `sodium`, `tokenizer`, and `xml`; Composer 2 and Node.js/npm compatible with `package-lock.json` are build prerequisites.
- MySQL 8 with InnoDB, `utf8mb4`, strict SQL mode, binary logging, and UTC system timestamps. Use a dedicated application user limited to the production schema; backup/restore credentials are separate and more restricted.
- Nginx and PHP-FPM start from [the reviewed examples](../../deploy/nginx/omni.conf.example) and [PHP-FPM pool](../../deploy/php-fpm/omni.conf.example). Validate with `nginx -t` and `php-fpm8.3 -t` before reload.
- A trusted CA certificate is mandatory. Port 80 only redirects to HTTPS. TLS 1.2 or newer, secure session cookies, and the application security headers are required.

## Files, identities, and secrets

Create an unprivileged `omni` service account. Release source is owned by the deployment account and is read-only to PHP-FPM. Only shared `storage` and `bootstrap/cache` are writable by `omni`; directories use `0750` and files `0640`. Never expose `storage/app/private`, backups, `.env`, logs, or restore-test files under the web root.

Copy `.env.production.example` to the host secret store or `/var/www/omni/shared/.env`, then inject real values there. Never commit `.env`, `.env.production`, application keys, database credentials, backup keys, mail credentials, or TLS private keys. Generate a unique production key with `php artisan key:generate --show`; store it in the approved secret manager and do not rotate it casually because encrypted application data depends on it.

Required release values are `APP_ENV=production`, `APP_DEBUG=false`, an HTTPS `APP_URL`, unique `APP_KEY`, `APP_VERSION=<immutable-git-tag-or-commit>`, and `APP_DEPLOYED_AT=<ISO-8601-Asia/Manila>`. Use `LOG_CHANNEL=daily` with retention and operating-system rotation for Nginx/PHP-FPM logs.

## Services

Install one cron entry owned by `omni`:

```cron
* * * * * cd /var/www/omni/current && /usr/bin/php artisan schedule:run --no-interaction >> /dev/null 2>&1
```

Confirm `php artisan schedule:list` shows daily, weekly, and monthly verified backups. There are currently no queued application jobs, so do not run a queue worker. If queued work is introduced by a later approved package, add a supervised `queue:work` process with `--sleep=3 --tries=3 --timeout=60`, ensure `retry_after` exceeds its timeout, and document restart/health checks.

Configure logrotate for `/var/log/nginx/omni-*.log` and PHP-FPM logs (daily, 14 generations, compressed). Laravel's `daily` channel retains application logs according to `LOG_DAILY_DAYS`. Monitor disk capacity so logs and backups cannot exhaust the application volume.

## Reviewed release procedure

1. Owner approves the immutable tag and maintenance window. Record tag, commit, operator, approval, planned migration list, and rollback target in the deployment record.
2. On CI/build host, check out the tag and run `composer validate --strict`, `composer audit`, `npm audit --omit=dev`, the full Pest suite, Pint, PHPStan, and `npm ci && npm run build`. Stop on any failure.
3. Review every pending migration. Explicitly identify table/column drops, renames, irreversible data changes, locks, and backfills. Destructive or non-reversible work requires a separately approved migration plan.
4. Create `/var/www/omni/releases/<tag>` from the approved source. Run `composer install --no-dev --optimize-autoloader --no-interaction` there and deploy the already validated `public/build` output. Link shared `.env`, `storage`, and persistent private files; apply least-privilege ownership/modes.
5. Before any migration, from the current release run `php artisan backup:run --class=pre_deployment`. Record the verified backup ID and independently confirm its off-site copy. Stop if verification fails.
6. Put the current release in maintenance mode with a secret: `php artisan down --render="errors::503" --secret="<one-time-secret>"`. Keep the old release and database backup intact.
7. In the candidate release run `php artisan optimize:clear`, `php artisan config:cache`, `php artisan route:cache`, `php artisan view:cache`, `php artisan about`, `php artisan route:list`, and `php artisan schedule:list`. Confirm the About output shows the approved version and deployment time.
8. Run `php artisan migrate:status`, then `php artisan migrate --force`. Do not use `migrate:fresh`, seed production, or continue past a migration error.
9. Smoke-test the candidate through a restricted host mapping: `/up`, login, dashboard, one read-only sales invoice, supplier invoice, stock ledger, general ledger, tax dashboard, and an authorized private attachment. Confirm no debug output and inspect logs.
10. Atomically repoint `/var/www/omni/current` to the candidate, reload PHP-FPM to clear OPcache, run `php artisan up`, and repeat health/login smoke checks through the public HTTPS endpoint.
11. Record actual activation time, tag/commit, migration batch, backup ID, operator, approver, cache/build checks, smoke results, and any incident. Create the signed release tag only from the reviewed commit; never move an existing production tag.

## Rollback

For an application-only fault, enter maintenance mode, atomically repoint `current` to the prior release, reload PHP-FPM, clear and rebuild caches under that release, smoke-test, and return service only with owner approval.

Do not automatically run `migrate:rollback` in production. If the release migrated data or schema, stop writes and use the reviewed migration-specific reversal. Prefer a forward corrective migration. When restoration is required, provision a clean database, restore the verified pre-deployment backup using the disaster-recovery procedure, reconcile record counts and financial balances, then repoint the application. Never restore over the only production database. Record the failed release, decision, data-loss window, recovery checks, and owner approval.

## Deployment checklist

- [ ] Owner approval, operator, tag, commit, and window recorded
- [ ] CI tests, Pint, PHPStan, dependency audits, and asset build passed
- [ ] Production requirements, PHP extensions, TLS, Nginx, PHP-FPM, MySQL, disk, and time synchronization checked
- [ ] Secrets injected outside source control; production key and backup key recoverable
- [ ] Permissions checked; only `storage` and `bootstrap/cache` writable
- [ ] Pending migrations reviewed for destructive/locking behavior
- [ ] Verified pre-deployment backup and off-site copy recorded
- [ ] Maintenance mode enabled before migration/activation
- [ ] Config, route, and view caches built only after validation
- [ ] Migration, About/version, routes, scheduler, and health checks passed
- [ ] Release activated atomically and smoke-tested over HTTPS
- [ ] Deployment record completed and owner approved service return
- [ ] Prior release and verified backup retained through the observation window
