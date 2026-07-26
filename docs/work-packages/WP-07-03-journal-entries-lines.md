# WP-07-03 — Journal Entries and Lines

## Objective

Create balanced journal entries and lines with draft, posted, and voided lifecycle controls. Reserve the reversal metadata needed by WP-07-06, but do not implement reversal operations in this work package.

## Read First

- AGENTS.md
- docs/phases/PHASE-07-ACCOUNTING-ENGINE.md
- docs/work-packages/WP-07-02-chart-of-accounts.md

## Scope

Create journal-entry headers and lines containing:

### Header

- journal_number
- journal_date
- document_date
- fiscal_period
- journal_type
- source_type
- source_id
- reference_number
- description
- total_debit
- total_credit
- status
- posted_at
- posted_by
- reversed_at
- reversed_by
- reversal_entry_id
- voided_at
- voided_by
- void_reason

### Lines

- account_id
- line_number
- description
- debit
- credit
- customer_id, optional
- supplier_id, optional
- financial_account_id, optional
- warehouse_id, optional
- product_id, optional
- source_line_type, optional
- source_line_id, optional

## Functional Requirements

- Draft manual entries may be edited.
- Posted entries are immutable.
- Each line must contain debit or credit, not both.
- Zero-value lines are rejected.
- Header totals are calculated server-side.
- Posting requires exact balance.
- Posting date must be in an open period.
- Controlled manual entries require permission.
- Source links are unique where automatic posting applies.
- Do not permit hard deletion of posted entries.
- Reversal columns and status values are schema foundations only; reversal creation, transitions, authorization behavior, and tests belong to WP-07-06.

## Permissions

- journals.view
- journals.create
- journals.update
- journals.post
- journals.reverse (registered for later use by WP-07-06)
- journals.void
- journals.view-sensitive

## Tests

- Balanced draft and posting
- Unbalanced rejection
- Debit/credit mutual exclusion
- Closed-period rejection
- Posted immutability
- Unique source posting
- Authorization
- Decimal precision

## Acceptance Criteria

1. Journal entries and lines are balanced.
2. Draft, posted, and voided lifecycle controls are enforced; reversal metadata is ready for WP-07-06.
3. Source links and posting periods are validated.
4. Posted entries are immutable.
5. Tests and fresh migrations pass.

## General Requirements

- Follow `AGENTS.md`.
- Use Laravel 13, Blade, Form Requests, policies, services/actions, and focused Pest tests.
- Use decimal-safe calculations and balanced-entry validation.
- Use database transactions and row locking for posting. Reversal, closing, and reopening operations are implemented by their dedicated later work packages.
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
