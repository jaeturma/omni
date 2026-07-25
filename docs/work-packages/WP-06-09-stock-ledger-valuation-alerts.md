# WP-06-09 — Stock Ledger, Valuation, and Alerts

## Objective

Provide read-only inventory reports, valuation, reorder alerts, and source traceability from posted movements.

## Read First

- AGENTS.md
- docs/phases/PHASE-06-INVENTORY-AND-STOCK-CONTROL.md
- docs/work-packages/WP-06-08-weighted-average-costing.md

## Scope

Create reports for:

- stock on hand
- stock by warehouse
- stock ledger
- stock card
- movement history
- inventory valuation
- low-stock and reorder alerts
- negative-stock exception report
- in-transit inventory
- slow-moving and no-movement items
- damaged and adjusted stock
- source-document traceability
- opening, movement, and closing quantity/value by date range

## Functional Requirements

- Support as-of date and date range.
- Exclude voided movements.
- Use posted movements only.
- Support product, category, brand, warehouse, and movement-type filters.
- Show quantity, unit cost, and value.
- Provide print-friendly and CSV output.
- Use pagination and efficient queries.
- Avoid storing duplicate report snapshots unless explicitly justified.
- Use configured product reorder levels.

## Permissions

- inventory-reports.view
- inventory-reports.export
- inventory-valuation.view
- inventory-cost.view

## Tests

- Stock-on-hand accuracy
- As-of date
- Valuation accuracy
- Transfer neutrality across all warehouses
- Voided exclusion
- Reorder alert
- In-transit quantity
- Authorization and cost-visibility restrictions

## Acceptance Criteria

1. Stock reports reconcile to movements.
2. Valuation matches weighted-average balances.
3. As-of-date reporting works.
4. Alerts and filters work.
5. Sensitive cost visibility is permission-controlled.
6. Tests pass.
7. No financial statement or ledger is created.

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
