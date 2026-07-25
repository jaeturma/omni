# WP-10-07 — Deployment and Environment Governance

## Objective

Create a reproducible, secure, and reversible production deployment process.

## Read First

- AGENTS.md
- docs/PROJECT_CONTEXT.md
- docs/phases/PHASE-10-PRODUCTION-READINESS-AND-HARDENING.md

## Scope

Document and validate:

- Production server requirements
- PHP extensions
- Nginx or Apache configuration
- PHP-FPM settings
- MySQL configuration
- Filesystem permissions
- Environment variables
- HTTPS
- Scheduler
- Queue worker if used
- Log rotation
- Backup scheduling
- Maintenance mode
- Migration procedure
- Rollback procedure
- Release tagging
- Deployment checklist

## Functional Requirements

- `.env` must not be committed.
- Production uses `APP_ENV=production`.
- Production uses `APP_DEBUG=false`.
- HTTPS is required.
- Application key is unique and protected.
- Storage and cache permissions are least-privilege.
- Deployment runs database backup before migrations.
- Migrations are reviewed for destructive changes.
- Build assets before release activation.
- Cache configuration, routes, and views only after validation.
- Provide rollback instructions.
- Record deployed version and deployment time.
- Avoid automatic deployment without explicit owner approval.

## Required Checks

```bash
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
npm run build
php artisan about
php artisan route:list
```

Adjust commands according to the actual deployment design.

## Acceptance Criteria

1. Production environment requirements are documented.
2. Secrets remain outside source control.
3. Deployment is repeatable.
4. Backup and rollback are included.
5. Production caches and scheduler are validated.
6. Deployment version is traceable.

## General Requirements

- Follow `AGENTS.md`.
- Use Laravel 13, Blade, Form Requests, policies, services/actions, and focused Pest tests.
- Keep the application as a modular monolith.
- Prefer framework-native features before adding packages.
- Document every new dependency and why it is required.
- Do not weaken financial immutability, auditability, or period controls.
- Do not expose secrets, TINs, bank details, or private attachments unnecessarily.
- Do not introduce speculative AI, mobile, payroll, CRM, or marketplace modules.
- Do not modify unrelated modules.

## Completion Report

Report only:

1. Files changed
2. Commands and tests run
3. Acceptance criteria result
4. Remaining issues requiring owner action
