# Phase 4 — Purchasing and Expenses

## Objective

Implement Omni Mini-ERP's operational purchasing, supplier billing, supplier payment, expense, payable monitoring, and procurement-document workflow.

This phase must create reliable purchase and expense records without yet implementing inventory valuation, cost of sales, general-ledger posting, financial statements, or BIR return calculations.

## Dependencies

- Phase 0 completed
- Phase 1 completed and validated
- Phase 2 completed and validated
- Phase 3 completed and validated
- Suppliers, products, services, units, payment methods, banks, document sequences, fiscal periods, permissions, and settings are available

## Work Packages

1. WP-04-01 — Purchasing Settings and Workflow
2. WP-04-02 — Purchase Requests and Canvass
3. WP-04-03 — Purchase Orders
4. WP-04-04 — Receiving Records
5. WP-04-05 — Supplier Invoices
6. WP-04-06 — Supplier Payments and Allocations
7. WP-04-07 — Operating Expenses
8. WP-04-08 — Accounts Payable and Aging
9. WP-04-09 — Purchasing Attachments and Traceability
10. WP-04-10 — Phase 4 Validation and Gap Review

## Phase Boundaries

This phase may create operational payable balances but must not implement:

- Inventory valuation or weighted-average costing
- Cost of goods sold
- General-ledger posting
- Financial statements
- Percentage-tax or income-tax return calculations
- BIR electronic filing
- Payroll
- Fixed-asset depreciation

## Definition of Done

Phase 4 is complete only when:

- All work packages are complete.
- Purchase requests, canvass records, purchase orders, receipts, supplier invoices, payments, expenses, payables, attachments, and traceability are operational.
- Document statuses and permissions are enforced.
- Decimal calculations are tested.
- Fresh migrations pass.
- Full tests pass.
- Pint passes.
- PHPStan passes.
- Frontend build passes.
- No Phase 5 or later module was implemented.
