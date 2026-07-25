# Phase 5 — Cash and Banking

## Objective

Implement Omni Mini-ERP's operational cash, bank, e-wallet, petty cash, transfer, and reconciliation workflows.

This phase must provide reliable cash-position and bank-activity records without yet implementing general-ledger posting, financial statements, tax return calculations, payroll, or inventory costing.

## Dependencies

- Phase 0 completed
- Phase 1 completed and validated
- Phase 2 completed and validated
- Phase 3 completed and validated
- Phase 4 completed and validated
- Payment methods, banks, document sequences, fiscal periods, permissions, sales collections, supplier payments, and operating expenses are available

## Work Packages

1. WP-05-01 — Cash and Bank Settings
2. WP-05-02 — Financial Accounts
3. WP-05-03 — Cash Receipts
4. WP-05-04 — Cash Disbursements
5. WP-05-05 — Fund Transfers
6. WP-05-06 — Petty Cash
7. WP-05-07 — Bank Statement Imports
8. WP-05-08 — Bank Reconciliation
9. WP-05-09 — Cash Position and Reports
10. WP-05-10 — Phase 5 Validation and Gap Review

## Phase Boundaries

This phase may maintain operational account balances but must not implement:

- General ledger or journal posting
- Financial statements
- Tax return calculations
- Payroll disbursement
- Loan amortization schedules
- Investment accounting
- Inventory valuation or cost of goods sold

## Definition of Done

Phase 5 is complete only when:

- All work packages are complete.
- Financial accounts, receipts, disbursements, transfers, petty cash, statement imports, reconciliation, and cash-position reports are operational.
- Status, permission, and immutable-posting rules are enforced.
- Operational balances reconcile.
- Fresh migrations pass.
- Full tests pass.
- Pint passes.
- PHPStan passes.
- Frontend build passes.
- No Phase 6 or later module was implemented.
