# WP-09-08 — Filing, Payment, and Attachment History

## Objective

Record the manual filing, payment, confirmation, amendment, and attachment history of tax returns.

## Read First

- AGENTS.md
- docs/phases/PHASE-09-BIR-TAX-PREPARATION-AND-COMPLIANCE.md
- docs/work-packages/WP-09-07-books-accounts-supporting-schedules.md

## Scope

Create filing and payment records containing:

- tax period and form
- worksheet revision
- filing channel
- filing date
- return reference or confirmation number
- amended flag
- amendment reason
- amount declared
- payment channel
- payment date
- payment reference
- amount paid
- bank or payment provider
- proof-of-filing attachment
- proof-of-payment attachment
- acknowledgement attachment
- filed_by
- reviewed_by
- notes

## Functional Requirements

- Support eBIRForms, eFPS, authorized-agent-bank, online payment, and other configurable channels.
- Record filing only after user confirmation.
- Do not simulate BIR acknowledgement.
- Prevent duplicate active filing records for the same worksheet revision.
- Support amended returns linked to original filing.
- Reconcile declared amount to worksheet.
- Reconcile payment amount to amount payable.
- Show unpaid, partially paid, paid, and overpaid statuses.
- Store files privately.
- Preserve immutable filing history.

## Permissions

- tax-filings.view
- tax-filings.record
- tax-filings.amend
- tax-payments.record
- tax-attachments.view
- tax-attachments.upload

## Tests

- Filing recording
- Duplicate prevention
- Worksheet reconciliation
- Full and partial payment
- Amendment linkage
- Private attachments
- Authorization
- Immutable history

## Acceptance Criteria

1. Filing and payment history is complete and immutable.
2. References and attachments are traceable.
3. Amounts reconcile to worksheets.
4. Amendments link to originals.
5. Tests and fresh migrations pass.

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
