# WP-10-09 — Production Cutover and Initial Balances

## Objective

Prepare and execute a controlled production cutover, including verified opening balances and document sequences.

## Read First

- AGENTS.md
- docs/DATA_MODEL.md
- docs/TAX_PROFILE.md
- docs/phases/PHASE-10-PRODUCTION-READINESS-AND-HARDENING.md
- docs/work-packages/WP-06-02-inventory-opening-balances.md
- docs/work-packages/WP-07-06-reversals-adjusting-entries.md

## Scope

Prepare cutover for:

- Business and tax profile
- Users and permissions
- Customers
- Suppliers
- Products and services
- Units and categories
- Financial accounts
- Document sequences
- Fiscal periods
- Chart of accounts
- Cash opening balances
- Receivable opening balances
- Payable opening balances
- Inventory opening quantities and costs
- Owner capital
- Loans and other liabilities
- Tax credits and liabilities
- Outstanding withholding certificates
- Initial reconciliation

## Functional Requirements

- Use a cutover date.
- Freeze legacy/manual balances at the cutover date.
- Import or encode opening balances through controlled batches.
- Require source documents and reviewer sign-off.
- Ensure total debits equal total credits.
- Reconcile AR by customer.
- Reconcile AP by supplier.
- Reconcile inventory by item and warehouse.
- Reconcile cash to actual count and bank statements.
- Validate document sequence starting numbers.
- Prevent duplicate opening entries.
- Take a verified backup before final activation.
- Create a cutover report.

## Tests and Checks

- Opening trial balance
- AR reconciliation
- AP reconciliation
- Cash reconciliation
- Inventory reconciliation
- Owner equity reconciliation
- Tax-control reconciliation
- Document-sequence validation
- Duplicate prevention
- Rollback rehearsal

## Acceptance Criteria

1. Cutover date is explicit.
2. Opening balances are documented and approved.
3. Trial balance balances.
4. Subledgers reconcile.
5. Document sequences are correct.
6. Backup and rollback are verified.
7. Cutover report is complete.

## General Requirements

- Follow `AGENTS.md`.
- Use Laravel 13, Blade, Form Requests, policies, services/actions, and focused Pest tests.
- Keep the application as a modular monolith.
- Prefer framework-native features before adding packages.
- Document every new dependency and why it is required.
- Do not weaken financial immutability, auditability, or period controls.
- Do not expose secrets, TINs, bank details, or private attachments unnecessarily.
- Do not introduce speculative AI, mobile, payroll, CRM, or marketplace modules.
- Do not modify unrelated modules.

## Completion Report

Report only:

1. Files changed
2. Commands and tests run
3. Acceptance criteria result
4. Remaining issues requiring owner action
