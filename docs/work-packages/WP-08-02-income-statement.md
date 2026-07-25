# WP-08-02 — Income Statement

## Objective

Create a period-based income statement from posted journal entries.

## Read First

- AGENTS.md
- docs/phases/PHASE-08-FINANCIAL-REPORTING.md
- docs/work-packages/WP-08-01-reporting-settings-conventions.md

## Scope

Create an income statement showing:

- Revenue
- Sales returns and discounts
- Net sales
- Cost of sales
- Gross profit
- Operating expenses
- Operating income
- Other income
- Other expenses
- Net income before tax
- Income-tax expense, when manually posted
- Net income after tax, when available

## Functional Requirements

- Support date range and fiscal period.
- Support current period and year-to-date.
- Use posted journals only.
- Respect chart-of-accounts hierarchy.
- Display zero-balance accounts according to report setting.
- Support department, project, customer, product category, or other dimensions only when existing tagged journal data supports them.
- Provide drilldown from report line to account activity.
- Provide print-friendly and CSV/PDF-ready output.
- Do not calculate income tax automatically.

## Permissions

- income-statement.view
- income-statement.export
- income-statement.drilldown

## Tests

- Revenue and expense aggregation
- Contra-revenue handling
- Gross profit
- Net income
- Period and year-to-date behavior
- Voided and draft exclusion
- Drilldown reconciliation
- Authorization

## Acceptance Criteria

1. Income statement reconciles to the trial balance.
2. Gross profit and net income are accurate.
3. Period and year-to-date views work.
4. Drilldowns reconcile to report totals.
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
