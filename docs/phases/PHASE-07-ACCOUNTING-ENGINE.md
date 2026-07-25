# Phase 7 — Accounting Engine

## Objective

Implement Omni Mini-ERP's double-entry accounting foundation, including the chart of accounts, posting rules, journal entries, automatic posting, general ledger, trial balance, subledger reconciliation, and accounting-period controls.

This phase converts operational transactions from Sales, Purchasing, Cash and Banking, and Inventory into balanced accounting records.

## Dependencies

- Phase 0 completed
- Phase 1 completed and validated
- Phase 2 completed and validated
- Phase 3 completed and validated
- Phase 4 completed and validated
- Phase 5 completed and validated
- Phase 6 completed and validated
- Fiscal periods, users, permissions, operational transactions, inventory costing, and document sequences are available

## Work Packages

1. WP-07-01 — Accounting Settings and Conventions
2. WP-07-02 — Chart of Accounts
3. WP-07-03 — Journal Entries and Lines
4. WP-07-04 — Posting Rules and Account Mapping
5. WP-07-05 — Automatic Source Posting
6. WP-07-06 — Reversals and Adjusting Entries
7. WP-07-07 — General Ledger and Account Activity
8. WP-07-08 — Trial Balance and Subledger Reconciliation
9. WP-07-09 — Period Close, Lock, and Reopen
10. WP-07-10 — Phase 7 Validation and Gap Review

## Phase Boundaries

This phase may produce accounting balances and reports required to validate the ledger, but must not yet implement:

- Final income statement presentation
- Final balance sheet presentation
- Cash-flow statement
- Statement of changes in owner’s equity
- BIR return calculations or filing
- Payroll accounting
- Fixed-asset depreciation schedules
- Multi-company consolidation
- Foreign-currency remeasurement

## Definition of Done

Phase 7 is complete only when:

- All work packages are complete.
- Every posted journal entry is balanced.
- Operational source transactions post exactly once.
- Reversals and adjustments are auditable.
- General ledger and trial balance reconcile.
- Accounts receivable, accounts payable, cash, and inventory subledgers reconcile to control accounts.
- Period close and lock controls work.
- Fresh migrations pass.
- Full tests pass.
- Pint passes.
- PHPStan passes.
- Frontend build passes.
- No Phase 8 or later module was implemented.
