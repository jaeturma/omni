# WP-06-08 — Weighted-Average Costing

## Objective

Implement and validate weighted-average inventory costing per product and warehouse.

## Read First

- AGENTS.md
- docs/DATA_MODEL.md
- docs/phases/PHASE-06-INVENTORY-AND-STOCK-CONTROL.md
- docs/work-packages/WP-06-07-physical-counts-reconciliation.md

## Scope

Create or finalize the costing service that handles:

- opening balances
- purchase receipts
- adjustment-in
- physical-count gains
- transfers in
- sales issues
- adjustment-out
- physical-count losses
- transfers out
- reversals

## Core Formula

For inbound cost-bearing movement:

```text
New Average Cost =
((Existing Quantity × Existing Average Cost)
 + (Incoming Quantity × Incoming Unit Cost))
÷ New Quantity
```

## Required Rules

- Calculate per product and warehouse.
- Preserve issue cost at posting time.
- Outbound movements do not change average cost unless quantity becomes zero according to the approved rule.
- Reversals restore quantity and value consistently.
- Use decimal-safe arithmetic.
- Use row locking to prevent concurrent cost corruption.
- Prevent division-by-zero.
- Define zero-quantity residual-cost handling.
- Provide a controlled rebuild command or service for validation only.
- Do not create journal entries.

## Tests

- First receipt
- Multiple receipts at different costs
- Partial issues
- Zero-quantity behavior
- Adjustment in and out
- Transfers
- Reversals
- Concurrent inbound and outbound safety
- Cost rebuild matches live balances

## Acceptance Criteria

1. Weighted-average calculations are mathematically correct.
2. Cost is maintained per product and warehouse.
3. Issue costs are immutable.
4. Reversals restore value correctly.
5. Concurrency protections exist.
6. Tests and fresh migrations pass.

## General Requirements

- Follow `AGENTS.md`.
- Use Laravel 13, Blade, Form Requests, policies, and focused Pest tests.
- Use decimal-safe server-side calculations.
- Use database transactions and row locking for stock movements and costing.
- Use document sequences where applicable.
- Never hard-delete posted inventory movements.
- Preserve source document references, costing details, and user attribution.
- Do not implement general-ledger entries, financial statements, tax return filing, payroll, or fixed-asset depreciation.
- Do not modify unrelated modules.

## Completion Report

Report only:

1. Files changed
2. Commands and tests run
3. Acceptance criteria result
4. Remaining issues requiring owner action
