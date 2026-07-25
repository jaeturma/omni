# WP-08-03 — Balance Sheet

## Objective

Create an as-of-date balance sheet from posted journal entries.

## Read First

- AGENTS.md
- docs/phases/PHASE-08-FINANCIAL-REPORTING.md
- docs/work-packages/WP-08-02-income-statement.md

## Scope

Create a balance sheet showing:

### Assets

- Current assets
- Cash and cash equivalents
- Accounts receivable
- Inventory
- Creditable withholding tax
- Prepaid expenses
- Non-current assets
- Property and equipment
- Accumulated depreciation
- Other assets

### Liabilities

- Current liabilities
- Accounts payable
- Accrued expenses
- Tax liabilities
- Current loan obligations
- Non-current liabilities

### Owner’s Equity

- Owner’s capital
- Owner’s drawings
- Prior-year equity
- Current-year earnings

## Functional Requirements

- Support as-of date.
- Derive current-year earnings from income-statement activity when not yet formally closed.
- Respect current and non-current classification.
- Present contra accounts correctly.
- Verify Assets = Liabilities + Owner’s Equity.
- Show imbalance warning and block final-ready status when not balanced.
- Provide account drilldown.
- Provide print-friendly and export output.

## Permissions

- balance-sheet.view
- balance-sheet.export
- balance-sheet.drilldown

## Tests

- Asset, liability, and equity aggregation
- Current-year earnings
- Contra-asset handling
- As-of-date behavior
- Balance equation
- Imbalance warning
- Drilldown reconciliation
- Authorization

## Acceptance Criteria

1. Balance sheet balances.
2. Current-year earnings are presented correctly.
3. As-of-date behavior is accurate.
4. Contra accounts display properly.
5. Drilldowns reconcile.
6. Tests pass.

## General Requirements

- Follow `AGENTS.md`.
- Use Laravel 13, Blade, Form Requests, policies, services/actions, and focused Pest tests.
- Use posted journal entries as the accounting source of truth.
- Use decimal-safe calculations.
- Support as-of dates, date ranges, and fiscal-period filters.
- Preserve report reproducibility through explicit parameters.
- Do not hard-code BIR tax rules into financial reports.
- Do not implement BIR return filing, payroll, fixed-asset depreciation schedules, or multi-company consolidation.
- Do not modify unrelated modules.

## Completion Report

Report only:

1. Files changed
2. Commands and tests run
3. Acceptance criteria result
4. Remaining issues requiring owner action
