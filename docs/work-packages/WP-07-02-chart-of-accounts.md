# WP-07-02 — Chart of Accounts

## Objective

Create a controlled chart of accounts suitable for a Philippine non-VAT sole proprietorship engaged in ICT, office-supply, school-supply, and service activities.

## Read First

- AGENTS.md
- docs/phases/PHASE-07-ACCOUNTING-ENGINE.md
- docs/work-packages/WP-07-01-accounting-settings-conventions.md

## Scope

Create account records containing:

- account code
- account name
- account class
- account type
- normal balance
- parent account, optional
- is_header
- is_postable
- is_control_account
- control_account_type, optional
- active status
- system account flag
- description
- display order

## Initial Account Groups

### Assets

- Cash on Hand
- Petty Cash
- Cash in Bank
- E-Wallet
- Accounts Receivable
- Inventory
- Creditable Withholding Tax
- Prepaid Expenses
- Office Equipment
- Computer Equipment
- Accumulated Depreciation

### Liabilities

- Accounts Payable
- Accrued Expenses
- Percentage Tax Payable
- Withholding Tax Payable
- Loans Payable

### Owner’s Equity

- Owner’s Capital
- Owner’s Drawings
- Current Year Earnings
- Retained Earnings or Prior-Year Equity

### Income

- ICT Product Sales
- Office Supply Sales
- School Supply Sales
- Service Income
- Installation Income
- Other Business Income
- Sales Returns and Discounts

### Cost of Sales

- ICT Product Cost
- Office Supply Cost
- School Supply Cost
- Freight-In
- Direct Service Cost

### Expenses

- Internet and Communication
- Utilities
- Rent
- Fuel and Transportation
- Delivery and Freight
- Repairs and Maintenance
- Salaries and Wages
- Professional Fees
- Bank Charges
- Taxes and Licenses
- Advertising
- Office Supplies Expense
- Software and Hosting
- Meals and Representation
- Depreciation
- Miscellaneous Expense

## Functional Requirements

- Support hierarchy without excessive depth.
- Prevent posting to header accounts.
- Prevent duplicate account code.
- Protect system and control accounts from unsafe deletion or type changes.
- Support activation and deactivation.
- Seed a minimal default chart.
- Allow authorized customization.
- Prevent circular parent relationships.

## Permissions

- chart-of-accounts.view
- chart-of-accounts.create
- chart-of-accounts.update
- chart-of-accounts.activate
- chart-of-accounts.deactivate
- chart-of-accounts.view-balances

## Tests

- Account creation and hierarchy
- Duplicate-code prevention
- Circular-parent prevention
- Header-account posting restriction
- System-account protection
- Authorization
- Default seeder

## Acceptance Criteria

1. A minimal usable chart of accounts exists.
2. Hierarchy and posting rules work.
3. Control accounts are protected.
4. Default accounts are seeded deterministically.
5. Tests and fresh migrations pass.

## General Requirements

- Follow `AGENTS.md`.
- Use Laravel 13, Blade, Form Requests, policies, services/actions, and focused Pest tests.
- Use decimal-safe calculations and balanced-entry validation.
- Use database transactions and row locking for posting, reversal, closing, and reopening.
- Never hard-delete posted journal entries or ledger-affecting source links.
- Preserve source transaction references, posting metadata, and user attribution.
- Do not implement final financial statements, BIR return filing, payroll, fixed-asset depreciation, or consolidation.
- Do not modify unrelated modules.

## Completion Report

Report only:

1. Files changed
2. Commands and tests run
3. Acceptance criteria result
4. Remaining issues requiring owner action
