# WP-09-05 — BIR Form 1701Q Preparation Worksheet

## Objective

Create a quarterly income-tax preparation worksheet for a self-employed individual when the form is registered and applicable.

## Read First

- AGENTS.md
- docs/TAX_PROFILE.md
- docs/phases/PHASE-09-BIR-TAX-PREPARATION-AND-COMPLIANCE.md
- docs/work-packages/WP-09-04-bir-2551q-preparation.md

## Scope

Create a worksheet containing:

- taxable year and quarter
- taxpayer registration snapshot
- income-tax method or election snapshot
- cumulative gross sales or receipts
- sales returns, discounts, and allowances
- cost of sales
- gross income
- itemized deductions or optional-standard-deduction inputs according to effective rule
- taxable income
- graduated or other applicable income-tax computation
- prior-quarter taxable income and payments
- creditable withholding taxes
- other allowable credits
- penalties entered manually with authority and evidence
- total payable
- review and filing status
- source financial-report references

## Functional Requirements

- Support only tax methods explicitly configured in the tax profile.
- Do not infer an 8% election.
- Use reconciled accounting and financial-report data.
- Preserve cumulative-quarter behavior.
- Keep manual tax adjustments separate and auditable.
- Require evidence for creditable withholding tax.
- Freeze rule and data snapshots when ready to file.
- Support amendments through revisions.
- Export a preparation worksheet only.
- Do not calculate annual tax reconciliation in this work package.

## Permissions

- bir-1701q.view
- bir-1701q.prepare
- bir-1701q.review
- bir-1701q.approve
- bir-1701q.revise
- bir-1701q.export

## Tests

- Applicability based on registration
- Cumulative-quarter behavior
- Configured tax-method handling
- Prior-quarter payment
- Withholding-credit evidence
- Frozen snapshot
- Amendment revision
- Authorization
- Decimal accuracy

## Acceptance Criteria

1. 1701Q worksheet is generated only when applicable.
2. The configured income-tax method is respected.
3. Accounting and prior-quarter figures reconcile.
4. Credits require traceable evidence.
5. Ready versions are immutable.
6. Tests pass.

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
