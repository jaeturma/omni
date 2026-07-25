# WP-05-06 — Petty Cash

## Objective

Implement a simple imprest-style petty-cash workflow for small operating expenditures.

## Read First

- AGENTS.md
- docs/phases/PHASE-05-CASH-AND-BANKING.md
- docs/work-packages/WP-05-05-fund-transfers.md

## Scope

Create petty-cash funds, custodians, releases, vouchers, liquidations, and replenishments.

## Required Data

### Petty-cash fund

- financial account
- custodian
- approved fund limit
- current operational balance
- active status

### Petty-cash voucher

- voucher number and date
- payee
- expense category
- purpose
- amount released
- amount liquidated
- amount returned
- receipt availability
- source expense reference, optional
- draft, released, liquidated, overdue, and voided statuses

## Functional Requirements

- Use a dedicated petty-cash financial account.
- Prevent releases above available balance.
- Require liquidation.
- Track returned cash.
- Track missing receipts.
- Allow replenishment from another financial account.
- Link replenishment to approved liquidated vouchers.
- Prevent duplicate replenishment.
- Do not create journal entries.

## Permissions

- petty-cash.view
- petty-cash.manage-fund
- petty-cash.release
- petty-cash.liquidate
- petty-cash.replenish
- petty-cash.void

## Tests

- Fund creation
- Release and available-balance check
- Full and partial liquidation
- Cash return
- Missing receipt
- Replenishment
- Duplicate-replenishment prevention
- Authorization

## Acceptance Criteria

1. Petty cash follows an auditable imprest workflow.
2. Releases cannot exceed available balance.
3. Liquidation and returns reconcile.
4. Replenishment is traceable.
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
