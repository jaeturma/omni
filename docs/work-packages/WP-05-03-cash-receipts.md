# WP-05-03 — Cash Receipts

## Objective

Record posted cash, bank, and e-wallet receipts from sales collections and other non-sales sources.

## Read First

- AGENTS.md
- docs/phases/PHASE-05-CASH-AND-BANKING.md
- docs/work-packages/WP-05-02-financial-accounts.md

## Scope

Create receipt records containing:

- receipt number
- receipt date
- fiscal period
- financial account
- receipt source type
- customer, optional
- customer payment reference, optional
- payer name
- payment method
- bank or e-wallet reference
- gross receipt
- deductions or fees
- net amount deposited
- notes
- draft, posted, cleared, bounced, and voided statuses

## Functional Requirements

- Link to customer payments when applicable.
- Allow other income or owner-capital receipts only with explicit source type.
- Prevent duplicate source posting.
- Update operational account balance on posting.
- Support clearing-date capture.
- Support bounced or reversed receipt handling.
- Require void reason.
- Do not create journal entries.

## Permissions

- cash-receipts.view
- cash-receipts.create
- cash-receipts.update
- cash-receipts.post
- cash-receipts.clear
- cash-receipts.void
- cash-receipts.print

## Tests

- Customer-payment linkage
- Other receipt source
- Duplicate-source prevention
- Balance update
- Clearing and bounced handling
- Voiding rollback
- Authorization

## Acceptance Criteria

1. Receipts may be source-linked or explicitly manual.
2. Account balances update transactionally.
3. Duplicate source posting is prevented.
4. Status and void controls work.
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
