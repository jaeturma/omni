# WP-05-07 — Bank Statement Imports

## Objective

Import bank or e-wallet statement lines into a staging area for reconciliation without changing posted system transactions.

## Read First

- AGENTS.md
- docs/phases/PHASE-05-CASH-AND-BANKING.md
- docs/work-packages/WP-05-06-petty-cash.md

## Scope

Create import batches and statement lines containing:

- financial account
- statement period
- source filename
- file hash
- imported_by and imported_at
- transaction date
- posting date
- description
- reference number
- debit
- credit
- running balance, optional
- normalized amount
- match status
- matched transaction reference, optional

## Functional Requirements

- Support CSV import first.
- Provide configurable column mapping.
- Validate account and date range.
- Prevent duplicate file import using hash and account.
- Preserve original imported values.
- Do not alter system transactions.
- Allow import rollback only before reconciliation finalization.
- Keep statement lines read-only after finalization.
- Do not use OCR.

## Permissions

- bank-statements.view
- bank-statements.import
- bank-statements.rollback
- bank-statements.export

## Tests

- Valid CSV import
- Invalid file rejection
- Duplicate-file prevention
- Column mapping
- Debit and credit normalization
- Rollback rules
- Authorization
- No mutation of operational transactions

## Acceptance Criteria

1. CSV statement imports are safe and repeatable.
2. Duplicate files are blocked.
3. Original data is preserved.
4. Import does not modify system transactions.
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
