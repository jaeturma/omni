# Phase 9 — BIR Tax Preparation and Compliance

## Objective

Implement Omni Mini-ERP's Philippine tax-preparation, reconciliation, filing-history, registered-books export, and compliance-document workflows for a non-VAT sole proprietorship.

The system must prepare reviewable worksheets and supporting schedules. It must not represent itself as an authorized direct BIR filing, payment, or computerized-books registration platform.

## Current Business Context

- Legal form: Sole proprietorship
- Registration: Non-VAT
- Business operations started: May 2026
- Initial reporting period: Second Quarter of 2026
- Initial percentage-tax return: BIR Form 2551Q
- Quarterly income-tax return may include BIR Form 1701Q according to the taxpayer's Certificate of Registration and applicable election
- Tax obligations must remain configurable

## Dependencies

- Phases 0 through 8 completed and validated
- Business and tax profiles available
- Fiscal periods and document sequences available
- Sales, purchases, expenses, cash, inventory, accounting, and financial reports validated
- Posted journal entries and reconciled subledgers available

## Work Packages

1. WP-09-01 — Tax Compliance Settings and Rule Registry
2. WP-09-02 — Tax Periods and Compliance Calendar
3. WP-09-03 — Sales and Receipt Tax Reconciliation
4. WP-09-04 — BIR Form 2551Q Preparation Worksheet
5. WP-09-05 — BIR Form 1701Q Preparation Worksheet
6. WP-09-06 — Withholding Certificates and Tax Credit Reconciliation
7. WP-09-07 — Books of Accounts and Supporting Schedules
8. WP-09-08 — Filing, Payment, and Attachment History
9. WP-09-09 — Tax Compliance Dashboard and Review Pack
10. WP-09-10 — Phase 9 Validation and Gap Review

## Phase Boundaries

This phase must not implement:

- Automated submission to eBIRForms, eFPS, ORUS, or other BIR systems
- Automatic tax payment
- Representation that generated books are already BIR-registered
- Legal or professional tax certification
- Payroll tax returns
- VAT returns
- Corporate income-tax returns
- Customs duties
- Local business-tax filing
- Automatic tax-rate updates from unverified sources

## Official Reference Principle

Use the current official BIR form list, tax reminders, eBIRForms availability, and secondary-registration guidance as external references during implementation. Store reference metadata and review dates rather than embedding assumptions permanently.

## Definition of Done

Phase 9 is complete only when:

- All work packages are complete.
- Tax periods and registered obligations are configurable.
- Sales, receipts, accounting, and withholding records reconcile.
- 2551Q and 1701Q preparation worksheets are reviewable and source-traceable.
- Filing and payment history is recorded.
- Books and supporting schedules can be exported.
- Missing documents and unresolved differences are visible.
- Full tests, Pint, PHPStan, migrations, and frontend build pass.
- No direct BIR filing or payment integration was implemented.
