# WP-00-01 — Repository and Laravel Baseline

## Objective

Verify that the existing `https://omni.app` project is a clean and functioning Laravel 13 Blade application, then establish the minimum repository baseline without implementing business modules.

## Read First

- AGENTS.md
- docs/PROJECT_CONTEXT.md
- docs/DEVELOPMENT_RULES.md

## Scope

- Verify Laravel version.
- Verify PHP version and required extensions.
- Verify MySQL configuration.
- Verify Blade and Vite.
- Verify Pest.
- Verify Git initialization.
- Verify application name, URL, locale, and timezone.
- Review `.env.example` for safe required placeholders.
- Remove only default files that are clearly unnecessary.
- Record detected environment issues.

## Out of Scope

- Authentication implementation
- Roles and permissions
- Business profile
- Tax profile UI
- Accounting tables
- Sales
- Purchasing
- Inventory
- Financial reports
- Tax calculations
- UI redesign
- Package installation unless strictly required for the baseline

## Required Checks

Run the commands applicable to the environment:

```bash
php artisan --version
php --version
composer validate
php artisan about
php artisan route:list
php artisan migrate:fresh
php artisan test
npm run build
git status
```

## Required Configuration

Confirm that the application uses:

- APP_NAME="Omni Mini-ERP"
- APP_URL=https://omni.app
- APP_TIMEZONE=Asia/Manila
- APP_LOCALE=en
- APP_FALLBACK_LOCALE=en
- APP_FAKER_LOCALE=en_PH
- MySQL database connection
- Mail logging for local development

Do not expose actual secrets in `.env.example`.

## Deliverables

- Verified Laravel 13 baseline
- Safe and complete `.env.example`
- Correct local application identity
- Successful database migration
- Passing baseline tests
- Successful frontend asset build
- Concise baseline completion report

## Acceptance Criteria

1. Laravel reports version 13.x.
2. PHP meets Laravel 13 requirements.
3. Required PHP extensions are available.
4. MySQL connection succeeds.
5. Fresh migrations complete.
6. Tests pass.
7. Frontend assets build.
8. The local URL is `https://omni.app`.
9. The timezone is `Asia/Manila`.
10. No business module is implemented.
11. No unnecessary package is added.
12. No unrelated file is modified.

## Completion Report

Report only:

1. Environment detected
2. Files changed
3. Commands and tests run
4. Acceptance criteria result
5. Remaining issues requiring owner action
