# WP-06-04 — Sales Delivery Stock Posting

## Objective

Post delivered inventory quantities from Phase 3 delivery records as stock issues.

## Read First

- AGENTS.md
- docs/phases/PHASE-06-INVENTORY-AND-STOCK-CONTROL.md
- docs/work-packages/WP-06-03-purchase-receipt-stock-posting.md
- docs/work-packages/WP-03-04-delivery-records.md

## Scope

Integrate delivered quantities with inventory issues.

## Functional Requirements

- Post only delivered or accepted quantities according to the approved delivery status.
- Post only inventory-tracked products.
- Ignore services and non-inventory items.
- Prevent duplicate posting of the same delivery line.
- Check available stock before posting.
- Block negative stock by default.
- Use current weighted-average cost for issue valuation.
- Create sales-issue movement and update stock balance transactionally.
- Reverse stock safely if a posted delivery is cancelled through an authorized workflow.
- Do not create cost-of-sales journal entries.

## Permissions

- inventory-issues.view
- inventory-issues.post
- inventory-issues.reverse

## Tests

- Full and partial issue
- Negative-stock prevention
- Duplicate-source prevention
- Service exclusion
- Cost capture at issue time
- Cancellation reversal
- Authorization
- Concurrency safety

## Acceptance Criteria

1. Delivered inventory decreases stock once.
2. Negative stock is blocked.
3. Issue cost is preserved.
4. Source traceability is complete.
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
