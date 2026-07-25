# WP-06-01 — Inventory Settings and Costing Conventions

## Objective

Establish inventory policies, movement types, status rules, costing conventions, and permissions before creating stock transactions.

## Read First

- AGENTS.md
- docs/PROJECT_CONTEXT.md
- docs/DEVELOPMENT_RULES.md
- docs/DATA_MODEL.md
- docs/phases/PHASE-06-INVENTORY-AND-STOCK-CONTROL.md

## Scope

- Define inventory movement types.
- Define posting and reversal statuses.
- Define negative-stock policy.
- Define quantity precision.
- Define weighted-average costing rules.
- Confirm document-sequence codes.
- Create or seed Phase 6 permissions.
- Define source-link rules for receiving and delivery records.
- Create shared enums or support classes only when clearly reusable.

## Initial Movement Types

- opening_balance
- purchase_receipt
- sales_issue
- customer_return
- supplier_return
- adjustment_in
- adjustment_out
- transfer_in
- transfer_out
- physical_count_gain
- physical_count_loss

## Required Rules

- Posted movements are append-only.
- Reversals create counter-movements.
- Negative stock is blocked by default.
- Cost precision uses `decimal(19, 4)`.
- Quantity precision uses `decimal(19, 4)`.
- Weighted-average cost is calculated per product and warehouse.
- Services never create stock movements.
- Non-inventory products never create stock movements.
- No general-ledger posting occurs in this phase.

## Tests

- Movement-type validation
- Permission seeding
- Negative-stock policy
- Service and non-inventory exclusions
- No stock transaction created

## Acceptance Criteria

1. Inventory conventions are centralized.
2. Movement and status types are explicit.
3. Weighted-average rules are documented.
4. Permissions exist.
5. No stock transaction or journal entry is implemented.

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
