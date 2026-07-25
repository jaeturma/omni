# WP-09-06 — Withholding Certificates and Tax Credit Reconciliation

## Objective

Reconcile customer withholding certificates and other tax-credit evidence to sales, collections, accounting control accounts, and tax worksheets.

## Read First

- AGENTS.md
- docs/TAX_PROFILE.md
- docs/phases/PHASE-09-BIR-TAX-PREPARATION-AND-COMPLIANCE.md
- docs/work-packages/WP-09-05-bir-1701q-preparation.md

## Scope

Support reconciliation for:

- BIR Form 2304
- BIR Form 2306
- BIR Form 2307
- Other configured certificate types

## Required Capabilities

- Match certificate to customer, invoice, payment, period, and accounting entry.
- Detect duplicates.
- Track pending, received, verified, rejected, applied, and voided statuses.
- Validate gross basis, rate, and tax withheld.
- Identify missing certificates.
- Identify certificates not yet applied.
- Prevent application to multiple returns beyond remaining available amount.
- Track partial application across periods where legally configured.
- Reconcile to creditable-withholding-tax and tax-withheld accounts.
- Preserve attachment and verification evidence.

## Permissions

- withholding-certificates.view
- withholding-certificates.create
- withholding-certificates.verify
- withholding-certificates.apply
- withholding-certificates.reject
- withholding-certificates.void
- withholding-reconciliation.export

## Tests

- Transaction matching
- Duplicate prevention
- Missing certificate
- Verification workflow
- Partial and full application
- Over-application prevention
- Accounting reconciliation
- Authorization

## Acceptance Criteria

1. Certificates reconcile to source transactions.
2. Duplicate and over-application are blocked.
3. Missing and unapplied certificates are visible.
4. Accounting control accounts reconcile.
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
