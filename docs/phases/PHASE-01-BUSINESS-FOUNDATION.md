# Phase 1 — Business Foundation

## Objective
Establish the business identity, tax configuration, fiscal periods, document numbering, user roles, and system settings required by all future modules.

## Dependencies
- Phase 0 completed
- WP-00-01 through WP-00-07 completed

## Work Packages
- WP-01-01 — Business Profile
- WP-01-02 — Tax Profile
- WP-01-03 — Fiscal Years and Periods
- WP-01-04 — Document Sequences
- WP-01-05 — Roles and User Administration
- WP-01-06 — System Settings
- WP-01-07 — Phase 1 Validation and Gap Review

## Phase Rules
- Follow `AGENTS.md`.
- Use Blade and standard Laravel conventions.
- Work on one work package at a time.
- Do not implement sales, purchasing, inventory, journal entries, financial reports, or tax calculations.
- Keep settings explicit and auditable.
- Avoid unnecessary packages.

## Definition of Done
- All seven work packages are complete.
- Fresh migrations, tests, Pint, PHPStan, and frontend build pass.
- Business identity and tax settings are configurable.
- Fiscal periods are generated and protected.
- Document numbering is concurrency-safe.
- User roles and permissions are enforced.
- System settings are available.
