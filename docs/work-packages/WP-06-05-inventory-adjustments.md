# WP-06-05 — Inventory Adjustments

## Objective

Create controlled stock-in and stock-out adjustments for damage, loss, correction, and other approved reasons.

## Read First

- AGENTS.md
- docs/phases/PHASE-06-INVENTORY-AND-STOCK-CONTROL.md
- docs/work-packages/WP-06-04-sales-delivery-stock-posting.md

## Scope

Create adjustment headers and lines containing:

- adjustment number and date
- fiscal period
- warehouse
- adjustment type
- reason code
- explanation
- product
- quantity
- unit cost where required
- total cost
- draft, approved, posted, and voided statuses
- approval, posting, and void metadata

## Initial Reason Codes

- damaged
- lost
- found
- encoding_error
- opening_balance_correction
- obsolete
- expired
- other

Keep reasons configurable.

## Functional Requirements

- Separate adjustment-in and adjustment-out.
- Require reason and explanation.
- Require approval before posting.
- Block stock-out above available quantity.
- Use current weighted-average cost for adjustment-out.
- Require explicit unit cost for adjustment-in when appropriate.
- Update stock and cost transactionally.
- Void through reversal only.
- Do not create journal entries.

## Permissions

- inventory-adjustments.view
- inventory-adjustments.create
- inventory-adjustments.approve
- inventory-adjustments.post
- inventory-adjustments.void

## Tests

- Adjustment in and out
- Approval requirement
- Negative-stock prevention
- Reason validation
- Cost behavior
- Reversal
- Authorization

## Acceptance Criteria

1. Adjustments are controlled and auditable.
2. Stock-out cannot exceed available quantity.
3. Costs are handled consistently.
4. Voiding creates reversals.
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
