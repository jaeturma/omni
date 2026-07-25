# WP-10-08 — User Acceptance Testing and Operating Procedures

## Objective

Validate real owner and bookkeeper workflows and create concise operating procedures.

## Read First

- AGENTS.md
- docs/phases/PHASE-10-PRODUCTION-READINESS-AND-HARDENING.md

## Scope

Create UAT scenarios for:

- Initial setup
- Customer and supplier creation
- Product and service creation
- Quotation to collection
- Purchase request to supplier payment
- Direct operating expense
- Bank receipt and disbursement
- Fund transfer
- Petty cash
- Inventory receipt and issue
- Physical count
- Journal review
- Financial statements
- 2551Q preparation
- 1701Q preparation when applicable
- Withholding certificate
- Books export
- Backup verification

## Required Operating Procedures

- Daily transaction entry
- Daily cash review
- Weekly receivable and payable review
- Monthly bank reconciliation
- Monthly inventory review
- Month-end accounting review
- Quarterly tax-preparation workflow
- Document attachment handling
- Voiding and reversal
- User management
- Backup verification
- Incident reporting

## Functional Requirements

- Record expected result and actual result.
- Record tester, date, status, and evidence.
- Classify defects by severity.
- Require resolution or accepted deferral.
- Create concise user guides with screenshots placeholders.
- Avoid duplicating technical developer documentation.
- Obtain owner sign-off before production cutover.

## Acceptance Criteria

1. All critical workflows have UAT scenarios.
2. UAT evidence is recorded.
3. Critical and high defects are resolved.
4. Operating procedures are documented.
5. Owner sign-off is captured.

## General Requirements

- Follow `AGENTS.md`.
- Use Laravel 13, Blade, Form Requests, policies, services/actions, and focused Pest tests.
- Keep the application as a modular monolith.
- Prefer framework-native features before adding packages.
- Document every new dependency and why it is required.
- Do not weaken financial immutability, auditability, or period controls.
- Do not expose secrets, TINs, bank details, or private attachments unnecessarily.
- Do not introduce speculative AI, mobile, payroll, CRM, or marketplace modules.
- Do not modify unrelated modules.

## Completion Report

Report only:

1. Files changed
2. Commands and tests run
3. Acceptance criteria result
4. Remaining issues requiring owner action
