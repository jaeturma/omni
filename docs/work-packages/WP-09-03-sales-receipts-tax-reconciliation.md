# WP-09-03 — Sales and Receipt Tax Reconciliation

## Objective

Reconcile sales invoices, collections, general-ledger revenue, customer deductions, and tax-period totals before preparing tax returns.

## Read First

- AGENTS.md
- docs/TAX_PROFILE.md
- docs/phases/PHASE-09-BIR-TAX-PREPARATION-AND-COMPLIANCE.md
- docs/work-packages/WP-09-02-tax-periods-compliance-calendar.md

## Scope

Create reconciliation schedules for:

- issued sales invoices
- voided invoices
- credit adjustments
- cash sales
- credit sales
- collections
- government sales
- private sales
- gross sales by date
- revenue accounts
- customer withholding
- invoice-sequence gaps
- unposted transactions
- differences between operational sales and accounting revenue

## Functional Requirements

- Support accrual and cash/receipt comparison views without assuming tax treatment.
- Clearly identify the selected tax-base rule.
- Preserve gross sales before withholding.
- Exclude voided transactions.
- Show manual reconciliation adjustments separately.
- Require reason, evidence, reviewer, and approval for adjustments.
- Drill down to source documents.
- Detect duplicate and missing invoice numbers.
- Block a return from ready-to-file status while critical differences remain.

## Permissions

- tax-reconciliation.view
- tax-reconciliation.adjust
- tax-reconciliation.review
- tax-reconciliation.export

## Tests

- Gross sales reconciliation
- Voided exclusion
- Credit adjustment handling
- Government/private split
- Withholding separation
- Invoice sequence gaps
- Ledger difference detection
- Manual adjustment controls
- Authorization

## Acceptance Criteria

1. Sales and ledger totals reconcile or show explicit differences.
2. Gross sales remain separate from withholding.
3. Invoice gaps and unposted transactions are visible.
4. Adjustments are auditable.
5. Tests pass.

## General Requirements

- Follow `AGENTS.md`.
- Use Laravel 13, Blade, Form Requests, policies, services/actions, and focused Pest tests.
- Use posted accounting and validated operational records as sources.
- Use decimal-safe calculations.
- Keep tax rules, rates, form registrations, deadlines, and mappings configurable and effective-dated.
- Preserve every worksheet parameter, source transaction, adjustment, reviewer action, filing reference, and attachment.
- Treat generated figures as preparation worksheets subject to owner or qualified tax-professional review.
- Do not claim that the application directly files or pays taxes through BIR.
- Do not hard-code temporary tax rates or filing deadlines into transaction logic.
- Do not modify unrelated modules.

## Completion Report

Report only:

1. Files changed
2. Commands and tests run
3. Acceptance criteria result
4. Remaining issues requiring owner action
