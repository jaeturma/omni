# WP-03-08 — Government Sales Deductions and Withholding

## Objective

Track government-sale deductions and withholding while preserving gross sales and net collection separately.

## Read First

- AGENTS.md
- docs/TAX_PROFILE.md
- docs/phases/PHASE-03-SALES-AND-COLLECTIONS.md
- docs/work-packages/WP-03-07-receivables-aging.md

## Scope

Create configurable deduction and certificate records for examples such as:

- percentage tax withheld
- expanded withholding tax
- retention
- liquidated damages
- other government deduction
- BIR Forms 2304, 2306, 2307, or other certificate type

## Required Data

- customer, invoice, and optional payment
- deduction type
- certificate type and number
- certificate date and covered period
- gross basis
- configurable rate
- amount
- pending, received, verified, and voided statuses
- notes and optional attachment reference

## Functional Requirements

- Preserve original gross invoice amount.
- Show withholding and other deductions separately.
- Track missing certificates.
- Prevent unreasonable duplicates.
- Provide quarterly and customer summaries.
- Do not automatically claim credits or calculate BIR returns.

## Permissions

- government-deductions.view
- government-deductions.create
- government-deductions.update
- government-deductions.verify
- government-deductions.void

## Tests

- Gross-sale preservation
- Net receipt computation
- Configurable rates
- Certificate tracking and duplicates
- Missing certificate report
- Authorization and voiding
- No tax return generation

## Acceptance Criteria

1. Deductions are separate from gross sales.
2. Certificates and missing documents are traceable.
3. Rates remain configurable.
4. Tests and fresh migrations pass.
5. No BIR return or journal is generated.

## General Requirements

- Follow `AGENTS.md`.
- Use Laravel 13, Blade, Form Requests, policies, and focused Pest tests.
- Use decimal-safe server-side calculations.
- Use database transactions for posting and allocations.
- Use document sequences where applicable.
- Never hard-delete posted financial records.
- Do not implement general-ledger entries, inventory costing, financial statements, or BIR return filing.
- Do not modify unrelated modules.

## Completion Report

Report only:

1. Files changed
2. Commands and tests run
3. Acceptance criteria result
4. Remaining issues requiring owner action
