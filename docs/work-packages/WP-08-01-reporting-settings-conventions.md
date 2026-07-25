# WP-08-01 — Reporting Settings and Conventions

## Objective

Establish financial-reporting formats, classification rules, comparative-period rules, rounding conventions, and permissions before creating statements.

## Read First

- AGENTS.md
- docs/PROJECT_CONTEXT.md
- docs/DEVELOPMENT_RULES.md
- docs/DATA_MODEL.md
- docs/phases/PHASE-08-FINANCIAL-REPORTING.md

## Scope

- Define report basis and source-of-truth rules.
- Define debit and credit sign presentation.
- Define current and non-current classification.
- Define operating, investing, and financing cash-flow classes.
- Define comparative-period behavior.
- Define zero-balance visibility.
- Define rounding and subtotal behavior.
- Define print and export conventions.
- Create or seed Phase 8 permissions.
- Create shared report parameter value objects or support classes only when clearly reusable.

## Required Rules

- Posted journals are the source of truth.
- Trial balance must reconcile before final reports are marked ready.
- Reports support as-of dates and fiscal periods.
- Revenue and expense reports use period activity.
- Asset, liability, and equity reports use cumulative balances as of date.
- Contra accounts display correctly.
- Report rounding must not create material imbalance.
- Comparative reports use the same account mapping and basis.
- Report parameters must be visible on printed and exported output.

## Permissions

- financial-reports.view
- financial-reports.export
- financial-reports.view-sensitive
- financial-report-settings.manage

## Tests

- Sign presentation
- Contra-account behavior
- Current/non-current classification
- Comparative-period rules
- Rounding behavior
- Permission seeding
- No financial statement created

## Acceptance Criteria

1. Reporting conventions are centralized.
2. Report basis and sign rules are explicit.
3. Classifications are configurable where needed.
4. Permissions exist.
5. No statement is implemented in this work package.

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
