# WP-06-03 — Purchase Receipt Stock Posting

## Objective

Post accepted quantities from Phase 4 receiving records into inventory.

## Read First

- AGENTS.md
- docs/phases/PHASE-06-INVENTORY-AND-STOCK-CONTROL.md
- docs/work-packages/WP-06-02-inventory-opening-balances.md
- docs/work-packages/WP-04-04-receiving-records.md

## Scope

Integrate accepted receiving-record quantities with inventory movements.

## Functional Requirements

- Post only accepted quantities.
- Post only inventory-tracked products.
- Ignore services and non-inventory items.
- Use accepted unit cost from purchase-order or supplier-invoice context according to approved source priority.
- Prevent duplicate posting of the same receiving line.
- Create purchase-receipt movement and update stock balance transactionally.
- Recalculate weighted-average cost.
- Support partial receipt.
- Reverse stock safely if an accepted receipt is later cancelled through an authorized workflow.
- Do not create journal entries.

## Permissions

- inventory-receipts.view
- inventory-receipts.post
- inventory-receipts.reverse

## Tests

- Accepted quantity posting
- Partial receipt
- Duplicate-source prevention
- Service exclusion
- Weighted-average update
- Cancellation reversal
- Authorization
- Concurrency safety where practical

## Acceptance Criteria

1. Accepted receipts increase stock once.
2. Source traceability is preserved.
3. Weighted-average cost updates correctly.
4. Duplicate posting is blocked.
5. Reversal restores balances safely.
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
