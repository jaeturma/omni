# WP-05-05 — Fund Transfers

## Objective

Transfer funds safely between financial accounts using linked source and destination transactions.

## Read First

- AGENTS.md
- docs/phases/PHASE-05-CASH-AND-BANKING.md
- docs/work-packages/WP-05-04-cash-disbursements.md

## Scope

Create transfer records containing:

- transfer number
- transfer date
- fiscal period
- source financial account
- destination financial account
- amount
- transfer fee
- reference number
- notes
- draft, posted, in_transit, completed, failed, and voided statuses
- linked source transaction
- linked destination transaction

## Functional Requirements

- Prevent transfer to the same account.
- Validate sufficient operational balance where required.
- Create linked transfer-out and transfer-in records transactionally.
- Preserve in-transit state when source and destination dates differ.
- Separate transfer fee.
- Prevent partial creation.
- Safely reverse both sides on voiding.
- Do not create journal entries.

## Permissions

- fund-transfers.view
- fund-transfers.create
- fund-transfers.post
- fund-transfers.complete
- fund-transfers.fail
- fund-transfers.void

## Tests

- Same-account rejection
- Linked two-sided creation
- Insufficient balance
- In-transit handling
- Fee separation
- Failure and void rollback
- Authorization
- Concurrency safety where practical

## Acceptance Criteria

1. Transfers are atomic and two-sided.
2. Source and destination balances reconcile.
3. In-transit transfers are supported.
4. Fees remain separate.
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
