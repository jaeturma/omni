# WP-08-09 — Financial Dashboard and Report Pack

## Objective

Create a concise financial dashboard and downloadable management report pack using completed Phase 8 reports.

## Read First

- AGENTS.md
- docs/phases/PHASE-08-FINANCIAL-REPORTING.md
- docs/work-packages/WP-08-08-drilldowns-exports.md

## Scope

Create a dashboard showing:

- Cash and cash equivalents
- Accounts receivable
- Accounts payable
- Inventory value
- Current-month sales
- Current-quarter sales
- Gross profit
- Operating expenses
- Net income
- Overdue receivables
- Overdue payables
- Unreconciled bank items
- Failed accounting postings
- Open-period status

Create a report pack containing:

- Income statement
- Balance sheet
- Cash-flow statement
- Owner’s equity statement
- Trial balance summary
- AR aging summary
- AP aging summary
- Cash-position summary
- Inventory-valuation summary

## Functional Requirements

- Use explicit as-of and period parameters.
- Display last refresh or generation time.
- Show warnings when accounting reconciliation is incomplete.
- Prevent misleading dashboard values when critical accounting errors exist.
- Allow report-pack generation for a selected period.
- Keep dashboard queries efficient.
- Avoid decorative charts that do not add decision value.
- Do not create tax calculations.

## Permissions

- financial-dashboard.view
- financial-report-pack.generate
- financial-report-pack.download

## Tests

- Dashboard metric reconciliation
- Period filters
- Error and reconciliation warnings
- Permission enforcement
- Report-pack content
- Report-pack total consistency
- Query-count or performance checks where practical

## Acceptance Criteria

1. Dashboard metrics reconcile to source reports.
2. Critical accounting issues are visible.
3. Report packs contain the required reports.
4. Period parameters are explicit.
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
