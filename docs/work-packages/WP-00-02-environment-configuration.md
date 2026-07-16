# WP-00-02 — Environment Configuration

## Objective

Standardize the Laravel local-development configuration for the existing Omni Mini-ERP project without implementing business functionality.

## Read First

- AGENTS.md
- docs/PROJECT_CONTEXT.md
- docs/DEVELOPMENT_RULES.md
- docs/work-packages/WP-00-01-repository-baseline.md

## Dependencies

- WP-00-01 completed

## Scope

- Review `.env.example`.
- Confirm application identity and timezone configuration.
- Confirm MySQL connection placeholders.
- Confirm local mail uses the log driver.
- Confirm session, cache, and queue defaults are appropriate for a minimal local environment.
- Confirm local file storage configuration.
- Document owner actions required for `.env`.
- Do not expose real credentials.

## Recommended Local Defaults

```dotenv
APP_NAME="Omni Mini-ERP"
APP_ENV=local
APP_DEBUG=true
APP_URL=https://omni.app

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_PH
APP_TIMEZONE=Asia/Manila

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=omni
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

FILESYSTEM_DISK=local

MAIL_MAILER=log
MAIL_FROM_ADDRESS="noreply@omni.app"
MAIL_FROM_NAME="${APP_NAME}"
```

The actual database name and credentials remain owner-controlled.

## Out of Scope

- Production configuration
- Deployment automation
- Docker
- Redis
- Email provider integration
- Cloud storage
- Business modules
- Authentication features

## Required Checks

```bash
php artisan config:clear
php artisan cache:clear
php artisan about
php artisan migrate:fresh
php artisan test
```

## Acceptance Criteria

1. `.env.example` contains safe placeholders.
2. No secret is committed.
3. Application URL is `https://omni.app`.
4. Timezone is `Asia/Manila`.
5. MySQL is configured as the intended database.
6. Local mail writes to logs.
7. Fresh migrations pass.
8. Tests pass.
9. No business functionality is added.
