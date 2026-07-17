# WP-01-03 — Fiscal Years and Periods

## Objective
Create fiscal years, calendar-quarter mapping, and monthly accounting periods.

## Read First
- AGENTS.md
- docs/PROJECT_CONTEXT.md
- docs/DEVELOPMENT_RULES.md
- docs/DATA_MODEL.md
- docs/phases/PHASE-01-BUSINESS-FOUNDATION.md

## Dependencies
- WP-01-01 completed

## Scope
- Create fiscal years.
- Generate monthly periods.
- Identify calendar quarters.
- Support open, closed, and locked states.
- Prevent overlapping years and periods.
- Support the first partial year beginning May 2026.

## Suggested Tables
- fiscal_years
- fiscal_periods

## Required Rules
- Only one current fiscal year.
- Periods must remain inside their fiscal year.
- Locked periods cannot be casually reopened.
- Closing and locking require authorization.
- Do not implement accounting posting.

## Initial Year
- Start: 2026-05-01
- End: 2026-12-31
- Monthly periods begin in May 2026.
- Calendar quarter labels remain Q2, Q3, and Q4 as applicable.

## Permissions
- fiscal-years.view
- fiscal-years.create
- fiscal-periods.manage
- fiscal-periods.close
- fiscal-periods.lock

## Tests
- Fiscal-year creation
- Monthly generation
- Partial first year
- Overlap prevention
- One current year
- Unauthorized status denial
- Locked-period protection

## Acceptance Criteria
1. Fiscal years and monthly periods are generated correctly.
2. Calendar quarter numbers are stored correctly.
3. Partial first year is supported.
4. Period states and authorization are enforced.
5. Tests and fresh migrations pass.
6. No accounting module is implemented.
