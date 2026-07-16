# WP-00-06 — Database Conventions

## Objective

Document and verify database conventions before creating business tables.

## Read First

- AGENTS.md
- docs/PROJECT_CONTEXT.md
- docs/DEVELOPMENT_RULES.md
- docs/DATA_MODEL.md

## Dependencies

- WP-00-01 completed
- WP-00-02 completed
- WP-00-03 completed

## Scope

- Review existing migrations.
- Confirm naming conventions.
- Confirm decimal standards for money, quantity, and rates.
- Confirm foreign-key conventions.
- Confirm accounting date conventions.
- Confirm document-number uniqueness strategy.
- Confirm migration rollback and fresh migration behavior.
- Add no speculative business tables.

## Out of Scope

- Chart of accounts
- Sales tables
- Purchase tables
- Inventory tables
- Tax tables
- Audit package installation
- Multi-tenant architecture

## Required Checks

```bash
php artisan migrate:fresh
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
```

## Acceptance Criteria

1. Existing migrations use consistent Laravel conventions.
2. Fresh migrations pass.
3. Decimal conventions are documented.
4. Foreign-key conventions are documented.
5. No speculative business table is created.
6. No business logic is implemented.
