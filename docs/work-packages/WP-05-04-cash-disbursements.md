# WP-05-04 — Cash Disbursements

## Objective

Record posted cash, bank, and e-wallet disbursements from supplier payments, expenses, and other approved sources.

## Read First

- AGENTS.md
- docs/phases/PHASE-05-CASH-AND-BANKING.md
- docs/work-packages/WP-05-03-cash-receipts.md

## Scope

Create disbursement records containing:

- disbursement number
- disbursement date
- fiscal period
- financial account
- source type
- supplier payment or expense reference, optional
- payee
- payment method
- cheque or transfer reference
- gross settlement
- deductions or bank charges
- net cash out
- notes
- draft, posted, released, cleared, stopped, and voided statuses

## Functional Requirements

- Link to supplier payments and operating expenses when applicable.
- Allow other approved disbursements only with explicit source type.
- Prevent duplicate source posting.
- Update operational account balance on posting.
- Capture cheque release and clearing dates.
- Support stopped cheque or reversed transfer handling.
- Require void reason.
- Do not create journal entries.

## Permissions

- cash-disbursements.view
- cash-disbursements.create
- cash-disbursements.update
- cash-disbursements.post
- cash-disbursements.release
- cash-disbursements.clear
- cash-disbursements.stop
- cash-disbursements.void
- cash-disbursements.print

## Tests

- Supplier-payment linkage
- Expense linkage
- Duplicate-source prevention
- Balance reduction
- Cheque lifecycle
- Voiding rollback
- Authorization

## Acceptance Criteria

1. Disbursements link safely to approved sources.
2. Operational balances update transactionally.
3. Duplicate source posting is prevented.
4. Cheque and transfer statuses work.
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
