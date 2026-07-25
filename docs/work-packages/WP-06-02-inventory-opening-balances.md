# WP-06-02 — Inventory Opening Balances

## Objective

Create controlled opening stock quantities and costs for inventory items at the start of system use.

## Read First

- AGENTS.md
- docs/phases/PHASE-06-INVENTORY-AND-STOCK-CONTROL.md
- docs/work-packages/WP-06-01-inventory-settings-costing.md

## Scope

Create opening-balance batches and lines containing:

- batch number and date
- fiscal period
- warehouse
- product
- quantity
- unit cost
- total cost
- reference
- notes
- draft, posted, and voided statuses
- posted_by and posted_at
- voided_by, voided_at, and void_reason

## Functional Requirements

- Allow one controlled opening balance per product and warehouse unless an authorized correction workflow is used.
- Calculate total cost server-side.
- Post movements transactionally.
- Initialize stock quantity and weighted-average cost.
- Block services and non-inventory products.
- Prevent negative opening quantity or cost.
- Prevent editing after posting.
- Void through reversal movement only.

## Permissions

- inventory-opening.view
- inventory-opening.create
- inventory-opening.post
- inventory-opening.void

## Tests

- Valid opening balance
- Duplicate prevention
- Cost calculation
- Service exclusion
- Posting and immutability
- Reversal on void
- Authorization

## Acceptance Criteria

1. Opening balances initialize quantity and cost correctly.
2. Duplicate openings are controlled.
3. Posted balances are immutable.
4. Voiding creates reversal movements.
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
