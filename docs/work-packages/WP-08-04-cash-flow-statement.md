# WP-08-04 — Cash Flow Statement

## Objective

Create a cash-flow statement using a controlled indirect-method foundation, with optional direct-method operational detail when reliable source tagging exists.

## Read First

- AGENTS.md
- docs/phases/PHASE-08-FINANCIAL-REPORTING.md
- docs/work-packages/WP-08-03-balance-sheet.md

## Scope

Create a statement showing:

### Operating Activities

- Net income
- Non-cash adjustments
- Change in accounts receivable
- Change in inventory
- Change in prepaid expenses
- Change in accounts payable
- Change in accrued and tax liabilities
- Net cash from operating activities

### Investing Activities

- Purchase of equipment and other long-term assets
- Proceeds from disposal, when posted
- Other investing activity

### Financing Activities

- Owner capital contributions
- Owner drawings
- Loan proceeds
- Loan repayments
- Other financing activity

### Reconciliation

- Beginning cash and cash equivalents
- Net change in cash
- Ending cash and cash equivalents

## Functional Requirements

- Use posted journals and mapped cash-flow classifications.
- Support date range and fiscal period.
- Reconcile ending cash to the balance sheet.
- Provide mapping review for unclassified accounts.
- Prevent final-ready status while material cash-flow items remain unclassified.
- Provide drilldown to journal activity.
- Do not infer unsupported classifications silently.

## Permissions

- cash-flow-statement.view
- cash-flow-statement.export
- cash-flow-mapping.manage
- cash-flow-statement.drilldown

## Tests

- Operating, investing, and financing sections
- Working-capital changes
- Beginning and ending cash
- Balance-sheet reconciliation
- Unclassified-account warning
- Drilldown
- Authorization

## Acceptance Criteria

1. Cash-flow statement reconciles to ending cash.
2. Classifications are explicit.
3. Unclassified material activity is visible.
4. Drilldowns reconcile.
5. Tests pass.

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
