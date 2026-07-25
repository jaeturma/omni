# WP-08-05 — Statement of Changes in Owner’s Equity

## Objective

Create the statement of changes in owner’s equity for the sole proprietorship.

## Read First

- AGENTS.md
- docs/phases/PHASE-08-FINANCIAL-REPORTING.md
- docs/work-packages/WP-08-04-cash-flow-statement.md

## Scope

Create a statement showing:

- Beginning owner’s capital
- Additional capital contributions
- Net income or loss
- Owner drawings
- Prior-period adjustments, when posted
- Closing owner’s equity

## Functional Requirements

- Support date range and fiscal period.
- Reconcile net income to the income statement.
- Reconcile closing equity to the balance sheet.
- Separate owner drawings from business expenses.
- Present prior-period adjustments separately.
- Provide drilldown to journal entries.
- Provide print-friendly and export output.

## Permissions

- owner-equity-statement.view
- owner-equity-statement.export
- owner-equity-statement.drilldown

## Tests

- Beginning capital
- Contributions
- Net income
- Drawings
- Prior-period adjustments
- Balance-sheet reconciliation
- Authorization

## Acceptance Criteria

1. Owner’s equity statement reconciles to the balance sheet.
2. Net income reconciles to the income statement.
3. Drawings remain separate from expenses.
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
