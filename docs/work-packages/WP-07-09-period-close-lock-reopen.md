# WP-07-09 — Period Close, Lock, and Reopen

## Objective

Implement controlled accounting-period close, lock, and reopen workflows.

## Read First

- AGENTS.md
- docs/phases/PHASE-07-ACCOUNTING-ENGINE.md
- docs/work-packages/WP-07-08-trial-balance-subledger-reconciliation.md
- docs/work-packages/WP-01-03-fiscal-years-periods.md

## Scope

Extend fiscal-period controls to support accounting close.

## Pre-Close Checks

At minimum:

- unposted journal entries
- failed source postings
- unbalanced journal entries
- AR reconciliation differences
- AP reconciliation differences
- cash and bank reconciliation differences
- inventory reconciliation differences
- unresolved reversal issues
- open adjustment batches
- transactions dated outside valid periods

## Functional Requirements

- Generate pre-close checklist.
- Block close when critical checks fail.
- Allow authorized documented override only for explicitly approved non-critical checks.
- Record close date, user, checklist result, and notes.
- Prevent postings into closed periods.
- Lock a period after final review.
- Prevent ordinary reopening of locked periods.
- Require elevated permission and reason to reopen.
- Record all reopen activity.
- Support closing-entry generation only if explicitly designed and tested.
- Do not create final financial statements.

## Permissions

- accounting-periods.view
- accounting-periods.preclose
- accounting-periods.close
- accounting-periods.lock
- accounting-periods.reopen

## Tests

- Pre-close success and failure
- Failed-posting blocker
- Reconciliation blocker
- Closed-period posting rejection
- Lock behavior
- Reopen authorization and reason
- Audit metadata
- Concurrent close prevention

## Acceptance Criteria

1. Period close uses explicit checks.
2. Critical differences block close.
3. Closed and locked periods reject postings.
4. Reopening is controlled and auditable.
5. Tests and fresh migrations pass.

## General Requirements

- Follow `AGENTS.md`.
- Use Laravel 13, Blade, Form Requests, policies, services/actions, and focused Pest tests.
- Use decimal-safe calculations and balanced-entry validation.
- Use database transactions and row locking for posting, reversal, closing, and reopening.
- Never hard-delete posted journal entries or ledger-affecting source links.
- Preserve source transaction references, posting metadata, and user attribution.
- Do not implement final financial statements, BIR return filing, payroll, fixed-asset depreciation, or consolidation.
- Do not modify unrelated modules.

## Completion Report

Report only:

1. Files changed
2. Commands and tests run
3. Acceptance criteria result
4. Remaining issues requiring owner action
