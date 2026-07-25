# WP-07-08 — Trial Balance and Subledger Reconciliation

## Objective

Create trial-balance reports and reconcile accounting control accounts to operational subledgers.

## Read First

- AGENTS.md
- docs/phases/PHASE-07-ACCOUNTING-ENGINE.md
- docs/work-packages/WP-07-07-general-ledger-account-activity.md

## Scope

Create:

- unadjusted trial balance
- adjusted trial balance
- trial balance by period
- trial balance as of date
- accounts receivable reconciliation
- accounts payable reconciliation
- cash and bank reconciliation
- inventory valuation reconciliation
- withholding and tax-control reconciliation where data exists

## Functional Requirements

- Total debits must equal total credits.
- Support account hierarchy and postable-account detail.
- Show opening, movement, and closing balances.
- Compare control-account balance to operational subledger.
- Show reconciliation difference and source drilldown.
- Do not silently adjust differences.
- Provide export and print view.
- Preserve as-of-date consistency.

## Permissions

- trial-balance.view
- trial-balance.export
- subledger-reconciliation.view
- subledger-reconciliation.export

## Tests

- Balanced trial balance
- Opening and period movement
- AR reconciliation
- AP reconciliation
- Cash reconciliation
- Inventory reconciliation
- Difference detection
- As-of-date behavior
- Authorization

## Acceptance Criteria

1. Trial balance is balanced.
2. Control accounts reconcile to subledgers.
3. Differences are visible and traceable.
4. Reports support periods and as-of dates.
5. Tests pass.

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
