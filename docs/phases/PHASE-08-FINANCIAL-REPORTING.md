# Phase 8 — Financial Reporting

## Objective

Implement Omni Mini-ERP's core financial statements and management reports using the validated accounting engine as the source of truth.

This phase must produce accurate, reproducible, drillable, printable, and exportable financial reports without yet implementing BIR return preparation or electronic filing.

## Dependencies

- Phase 0 completed
- Phase 1 completed and validated
- Phase 2 completed and validated
- Phase 3 completed and validated
- Phase 4 completed and validated
- Phase 5 completed and validated
- Phase 6 completed and validated
- Phase 7 completed and validated
- Chart of accounts, journals, general ledger, trial balance, subledger reconciliation, and fiscal-period controls are available

## Work Packages

1. WP-08-01 — Reporting Settings and Conventions
2. WP-08-02 — Income Statement
3. WP-08-03 — Balance Sheet
4. WP-08-04 — Cash Flow Statement
5. WP-08-05 — Statement of Changes in Owner’s Equity
6. WP-08-06 — Comparative and Period Reports
7. WP-08-07 — Management Profitability Reports
8. WP-08-08 — Financial Report Drilldowns and Exports
9. WP-08-09 — Financial Dashboard and Report Pack
10. WP-08-10 — Phase 8 Validation and Gap Review

## Phase Boundaries

This phase may present finalized internal financial statements and management reports, but must not implement:

- BIR Form 2551Q computation
- BIR Form 1701Q computation
- Annual income-tax return preparation
- Electronic BIR filing or payment
- Payroll reports
- Fixed-asset depreciation engine
- Multi-company consolidation
- Foreign-currency translation
- External-audit opinion or certification

## Definition of Done

Phase 8 is complete only when:

- All work packages are complete.
- Income statement, balance sheet, cash flow, owner’s equity, comparative reports, management reports, drilldowns, exports, and report packs are operational.
- Reports reconcile to the trial balance and general ledger.
- As-of and period filters work.
- Report parameters are reproducible.
- Fresh migrations pass.
- Full tests pass.
- Pint passes.
- PHPStan passes.
- Frontend build passes.
- No Phase 9 or later module was implemented.
