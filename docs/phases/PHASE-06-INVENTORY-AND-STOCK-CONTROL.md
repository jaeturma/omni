# Phase 6 — Inventory and Stock Control

## Objective

Implement Omni Mini-ERP's inventory movement, stock balance, weighted-average costing, adjustment, transfer, count, and valuation workflows.

This phase integrates operational purchasing receipts and sales deliveries into stock records while still deferring general-ledger posting and financial statements.

## Dependencies

- Phase 0 completed
- Phase 1 completed and validated
- Phase 2 completed and validated
- Phase 3 completed and validated
- Phase 4 completed and validated
- Phase 5 completed and validated
- Products, units, warehouses, receiving records, delivery records, permissions, document sequences, and fiscal periods are available

## Work Packages

1. WP-06-01 — Inventory Settings and Costing Conventions
2. WP-06-02 — Inventory Opening Balances
3. WP-06-03 — Purchase Receipt Stock Posting
4. WP-06-04 — Sales Delivery Stock Posting
5. WP-06-05 — Inventory Adjustments
6. WP-06-06 — Warehouse Transfers
7. WP-06-07 — Physical Counts and Reconciliation
8. WP-06-08 — Weighted-Average Costing
9. WP-06-09 — Stock Ledger, Valuation, and Alerts
10. WP-06-10 — Phase 6 Validation and Gap Review

## Phase Boundaries

This phase may calculate operational inventory value and cost of issued stock but must not implement:

- General-ledger or journal posting
- Financial statements
- Tax return calculations
- Fixed-asset management
- Manufacturing or bill of materials
- Serial-number lifecycle unless explicitly approved later
- Batch or expiry management unless explicitly approved later

## Definition of Done

Phase 6 is complete only when:

- All work packages are complete.
- Opening balances, purchase receipts, sales issues, adjustments, transfers, counts, and costing are operational.
- Stock on hand and valuation reconcile.
- Negative-stock policy is enforced.
- Posted movements are immutable.
- Fresh migrations pass.
- Full tests pass.
- Pint passes.
- PHPStan passes.
- Frontend build passes.
- No Phase 7 or later module was implemented.
