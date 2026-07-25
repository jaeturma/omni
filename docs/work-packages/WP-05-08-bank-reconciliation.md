# WP-05-08 — Bank Reconciliation

## Objective

Match imported statement lines to posted system transactions and produce controlled bank-reconciliation records.

## Read First

- AGENTS.md
- docs/phases/PHASE-05-CASH-AND-BANKING.md
- docs/work-packages/WP-05-07-bank-statement-imports.md

## Scope

Create reconciliation headers and match records containing:

- financial account
- statement period
- statement opening balance
- statement closing balance
- system opening balance
- system closing balance
- unmatched deposits
- unmatched withdrawals
- bank charges
- interest or other statement-only items
- reconciliation difference
- draft, reviewed, finalized, and reopened statuses

## Functional Requirements

- Match one-to-one and limited one-to-many records where justified.
- Suggest matches using amount, date, and reference.
- Require user confirmation.
- Never silently create transactions from statement lines.
- Allow approved creation of explicit adjustment records only through a controlled action.
- Prevent finalization while unexplained difference remains, unless an authorized documented exception exists.
- Lock matches after finalization.
- Require reason and permission for reopening.
- Do not create general-ledger entries.

## Permissions

- bank-reconciliation.view
- bank-reconciliation.create
- bank-reconciliation.match
- bank-reconciliation.finalize
- bank-reconciliation.reopen

## Tests

- Exact matching
- Date-tolerance matching
- One-to-many handling
- Unmatched items
- Difference calculation
- Finalization controls
- Reopening
- Authorization

## Acceptance Criteria

1. Reconciliation matches system and statement activity safely.
2. Suggested matches require confirmation.
3. Differences are transparent.
4. Finalized reconciliations are locked.
5. Tests and fresh migrations pass.

## General Requirements

- Follow `AGENTS.md`.
- Use Laravel 13, Blade, Form Requests, policies, and focused Pest tests.
- Use decimal-safe server-side calculations.
- Use database transactions for posting, reconciliation, and balance changes.
- Use document sequences where applicable.
- Never hard-delete posted cash or bank transactions.
- Preserve source document references and user attribution.
- Do not implement general-ledger entries, financial statements, tax return filing, payroll, or inventory costing.
- Do not modify unrelated modules.

## Completion Report

Report only:

1. Files changed
2. Commands and tests run
3. Acceptance criteria result
4. Remaining issues requiring owner action
