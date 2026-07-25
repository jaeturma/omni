# WP-09-04 — BIR Form 2551Q Preparation Worksheet

## Objective

Create a source-traceable quarterly percentage-tax preparation worksheet for registered applicable tax periods.

## Read First

- AGENTS.md
- docs/TAX_PROFILE.md
- docs/phases/PHASE-09-BIR-TAX-PREPARATION-AND-COMPLIANCE.md
- docs/work-packages/WP-09-03-sales-receipts-tax-reconciliation.md

## Scope

Create a preparation worksheet containing:

- return year
- quarter
- original or amended return
- taxpayer registration snapshot
- tax type and applicable rule snapshot
- gross taxable sales or receipts
- excluded or exempt amounts, when supported
- taxable amount
- applicable percentage-tax rate
- gross tax due
- allowable percentage-tax credits
- percentage tax withheld by government
- prior payment or overpayment, when allowed
- penalties, surcharge, interest, or compromise entered manually with authority and evidence
- total amount payable
- filing status
- review status
- preparation notes
- source-reconciliation reference

## Functional Requirements

- Use effective-dated rules.
- Do not permanently assume a 3% rate.
- Show every source transaction included or excluded.
- Purchases and ordinary expenses must not automatically reduce the percentage-tax base.
- Distinguish tax withheld from sales discounts.
- Require reconciliation completion.
- Support original and amended worksheets.
- Freeze a rule and data snapshot when marked ready to file.
- Changes after freezing create a new revision.
- Export a review worksheet, not an official electronic submission file.
- Display a disclaimer that figures must be encoded or filed through the appropriate BIR channel.

## Permissions

- bir-2551q.view
- bir-2551q.prepare
- bir-2551q.review
- bir-2551q.approve
- bir-2551q.revise
- bir-2551q.export

## Tests

- Effective rate resolution
- Gross tax calculation
- Withholding-credit treatment
- Expense exclusion from tax base
- Original and amended revision
- Frozen snapshot
- Reconciliation blocker
- Authorization
- Decimal and rounding accuracy

## Acceptance Criteria

1. 2551Q worksheet uses configurable effective rules.
2. Source transactions are traceable.
3. Withholding and credits remain separate.
4. Ready-to-file versions are immutable.
5. Amendments create revisions.
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
