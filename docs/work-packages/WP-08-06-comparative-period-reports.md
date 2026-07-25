# WP-08-06 — Comparative and Period Reports

## Objective

Add comparative financial reporting across months, quarters, years, and custom periods.

## Read First

- AGENTS.md
- docs/phases/PHASE-08-FINANCIAL-REPORTING.md
- docs/work-packages/WP-08-05-owner-equity-statement.md

## Scope

Support comparisons for:

- Current month versus prior month
- Current quarter versus prior quarter
- Current period versus same period last year
- Year-to-date versus prior-year year-to-date
- Actual versus manually entered budget, only if a minimal budget reference already exists or is explicitly created within scope
- Custom period versus custom period

## Functional Requirements

- Use identical classification and account mapping for compared periods.
- Show absolute variance.
- Show percentage variance where mathematically valid.
- Handle zero and negative prior-period values safely.
- Allow income statement and balance sheet comparison.
- Support trend columns for selected periods.
- Preserve report parameters.
- Provide print-friendly and export output.
- Do not implement a full budgeting module unless separately approved.

## Permissions

- comparative-reports.view
- comparative-reports.export

## Tests

- Month and quarter comparison
- Same-period-prior-year comparison
- Absolute and percentage variance
- Zero denominator behavior
- Negative values
- Account mapping consistency
- Authorization

## Acceptance Criteria

1. Comparative periods use consistent classifications.
2. Variances are correct and safe.
3. Report parameters are reproducible.
4. Tests pass.

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
