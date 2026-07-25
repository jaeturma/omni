# WP-08-07 — Management Profitability Reports

## Objective

Create management reports that explain sales, margin, expense, and profitability performance using available operational and accounting dimensions.

## Read First

- AGENTS.md
- docs/phases/PHASE-08-FINANCIAL-REPORTING.md
- docs/work-packages/WP-08-06-comparative-period-reports.md

## Scope

Create reports for:

- Sales by customer
- Sales by customer type
- Sales by product
- Sales by product category
- Sales by service category
- Government versus private sales
- Gross profit by product or category
- Gross profit by customer
- Expense by category
- Expense by supplier or payee
- Collection performance
- Receivable turnover indicators, where supported
- Inventory turnover indicators, where supported
- Monthly profitability trend

## Functional Requirements

- Reconcile total sales and expenses to accounting reports.
- Use source dimensions attached to journal lines or operational source drilldowns.
- Clearly identify reports that use operational data rather than pure ledger dimensions.
- Avoid double-counting.
- Support filters and date ranges.
- Protect sensitive margin and cost data through permissions.
- Provide CSV and print-friendly output.

## Permissions

- management-reports.view
- management-reports.export
- profitability.view
- margin.view
- cost-data.view

## Tests

- Sales reconciliation
- Gross-profit calculation
- Expense reconciliation
- Government/private split
- Dimension filters
- Cost visibility restrictions
- No double-counting
- Authorization

## Acceptance Criteria

1. Management reports reconcile to core financial reports.
2. Gross-profit and margin calculations are accurate.
3. Sensitive cost data is permission-controlled.
4. Operational versus ledger sourcing is transparent.
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
