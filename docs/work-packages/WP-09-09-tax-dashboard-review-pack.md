# WP-09-09 — Tax Compliance Dashboard and Review Pack

## Objective

Create a concise tax-compliance dashboard and downloadable review pack for each tax period.

## Read First

- AGENTS.md
- docs/phases/PHASE-09-BIR-TAX-PREPARATION-AND-COMPLIANCE.md
- docs/work-packages/WP-09-08-filing-payment-attachment-history.md

## Scope

Create dashboard indicators for:

- upcoming obligations
- due soon
- overdue
- preparing
- for review
- ready to file
- filed but unpaid
- paid
- amended
- missing certificates
- unreconciled sales
- invoice-sequence gaps
- failed accounting postings
- unclosed periods
- missing filing or payment proof

Create a review pack containing:

- taxpayer and registration snapshot
- tax-period summary
- sales and receipt reconciliation
- 2551Q worksheet, when applicable
- 1701Q worksheet, when applicable
- withholding summary
- books and schedules index
- filing and payment history
- unresolved issues
- preparer and reviewer sign-off page

## Functional Requirements

- Use explicit tax period.
- Show last refresh and rule-review date.
- Block ready-to-file indication when critical reconciliation issues remain.
- Allow review comments and resolution status.
- Do not expose sensitive TIN or account numbers beyond authorized users.
- Provide printable and downloadable output.
- Do not file or pay automatically.

## Permissions

- tax-dashboard.view
- tax-review-pack.generate
- tax-review-pack.download
- tax-review-comments.manage

## Tests

- Dashboard status accuracy
- Critical blocker behavior
- Missing-document indicators
- Review-pack content
- Permission and sensitive-data masking
- Period parameter
- No filing side effect

## Acceptance Criteria

1. Compliance status is clear.
2. Critical issues block ready-to-file status.
3. Review packs contain all applicable schedules.
4. Sensitive data is protected.
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
