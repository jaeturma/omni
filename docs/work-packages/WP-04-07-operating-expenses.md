# WP-04-07 — Operating Expenses

## Objective

Create a simple operating-expense workflow for business costs that do not require a purchase order or supplier-invoice workflow.

## Read First

- AGENTS.md
- docs/TAX_PROFILE.md
- docs/phases/PHASE-04-PURCHASING-AND-EXPENSES.md
- docs/work-packages/WP-04-06-supplier-payments-allocations.md

## Scope

Create expense records containing:

- expense number and date
- fiscal period
- payee or supplier, optional
- expense category
- description and business purpose
- reference number
- payment method and optional bank
- gross amount
- withholding amount
- other deductions
- net cash paid
- reimbursable flag
- project or customer reference, optional
- receipt or invoice availability
- draft, approved, paid, voided, and reimbursable statuses

## Functional Requirements

- Create direct paid expenses.
- Create approved but unpaid expense claims where justified.
- Distinguish business expenses from owner drawings.
- Require business purpose.
- Support receipt or invoice reference.
- Prevent negative or zero amounts.
- Use document sequences.
- Require void reason.
- Do not create journal entries, payroll, or depreciation.

## Permissions

- expenses.view
- expenses.create
- expenses.update
- expenses.approve
- expenses.pay
- expenses.void
- expenses.print

## Tests

- Direct paid expense
- Unpaid approved expense
- Owner drawing exclusion or separate treatment
- Required business purpose
- Amount validation
- Authorization
- Voiding
- No accounting posting

## Acceptance Criteria

1. Operating expenses are recorded clearly.
2. Owner drawings are not treated as expenses.
3. Payment and withholding values remain separate.
4. Status and authorization rules work.
5. Tests and fresh migrations pass.

## General Requirements

- Follow `AGENTS.md`.
- Use Laravel 13, Blade, Form Requests, policies, and focused Pest tests.
- Use decimal-safe server-side calculations.
- Use database transactions for posting, allocations, and status changes.
- Use document sequences where applicable.
- Never hard-delete posted financial records.
- Preserve gross purchases, discounts, withholding, payments, and balances separately.
- Do not implement general-ledger entries, inventory costing, financial statements, or BIR return filing.
- Do not modify unrelated modules.

## Completion Report

Report only:

1. Files changed
2. Commands and tests run
3. Acceptance criteria result
4. Remaining issues requiring owner action
