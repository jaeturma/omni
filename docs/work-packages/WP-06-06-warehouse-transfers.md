# WP-06-06 — Warehouse Transfers

## Objective

Transfer inventory between warehouses using linked transfer-out and transfer-in movements.

## Read First

- AGENTS.md
- docs/phases/PHASE-06-INVENTORY-AND-STOCK-CONTROL.md
- docs/work-packages/WP-06-05-inventory-adjustments.md

## Scope

Create transfer headers and lines containing:

- transfer number and date
- source warehouse
- destination warehouse
- product
- quantity
- source unit cost
- total cost
- reference
- notes
- draft, approved, released, in_transit, received, completed, and voided statuses

## Functional Requirements

- Prevent same-warehouse transfer.
- Check source availability.
- Block negative stock.
- Create transfer-out on release.
- Create transfer-in on receipt.
- Preserve source cost across transfer.
- Support in-transit quantity.
- Prevent partial creation.
- Support partial receipt only if explicitly implemented and tested.
- Reverse both sides safely when voiding.
- Do not create journal entries.

## Permissions

- inventory-transfers.view
- inventory-transfers.create
- inventory-transfers.approve
- inventory-transfers.release
- inventory-transfers.receive
- inventory-transfers.void

## Tests

- Same-warehouse rejection
- Availability validation
- Linked out and in movements
- In-transit status
- Cost preservation
- Partial receipt, if supported
- Reversal
- Authorization

## Acceptance Criteria

1. Transfers are two-sided and traceable.
2. Source stock and destination stock reconcile.
3. In-transit inventory is visible.
4. Cost is preserved.
5. Tests and fresh migrations pass.

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
