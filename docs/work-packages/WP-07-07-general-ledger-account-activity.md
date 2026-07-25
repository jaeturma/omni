# WP-07-07 — General Ledger and Account Activity

## Objective

Provide read-only general-ledger and account-activity reports from posted journal entries.

## Read First

- AGENTS.md
- docs/phases/PHASE-07-ACCOUNTING-ENGINE.md
- docs/work-packages/WP-07-06-reversals-adjusting-entries.md

## Scope

Create reports for:

- general journal
- general ledger
- account activity
- account running balance
- journal source traceability
- customer-tagged account activity
- supplier-tagged account activity
- financial-account-tagged activity
- product and warehouse tagged activity where present
- opening, debit, credit, and closing balance by date range

## Functional Requirements

- Include posted entries only.
- Support as-of and date-range reporting.
- Respect normal-balance presentation.
- Exclude voided entries.
- Show reversals as separate entries.
- Support account hierarchy filters.
- Support source-type and reference filters.
- Provide print-friendly and CSV output.
- Use pagination and efficient queries.
- Prevent unauthorized viewing of sensitive balances.

## Permissions

- general-ledger.view
- general-ledger.export
- general-journal.view
- account-activity.view
- account-balances.view

## Tests

- Running balance
- Normal-balance presentation
- Opening and closing balances
- Reversal display
- Voided exclusion
- Source filters
- Authorization
- Totals reconciliation

## Acceptance Criteria

1. Ledger reports reconcile to posted journal lines.
2. Running balances are accurate.
3. Source traceability is available.
4. Sensitive balances are permission-controlled.
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
