# WP-10-04 — Data Privacy and Retention Controls

## Objective

Implement practical data-protection, retention, masking, archival, and controlled disposal rules.

## Read First

- AGENTS.md
- docs/TAX_PROFILE.md
- docs/phases/PHASE-10-PRODUCTION-READINESS-AND-HARDENING.md

## Scope

Classify and protect:

- Proprietor information
- Customer and supplier TINs
- Addresses and contacts
- Bank-account references
- User information
- Financial transactions
- Tax records
- Attachments
- Audit records
- Backups
- Logs

## Functional Requirements

- Create a data-classification registry.
- Define public, internal, confidential, and restricted classes.
- Mask sensitive fields in list views and exports.
- Restrict sensitive exports.
- Define retention periods as configurable policy records.
- Prevent deletion of records required for financial, tax, or audit history.
- Use archive or anonymization where lawful and appropriate.
- Provide controlled data-subject lookup and export only for authorized users.
- Prevent secrets or unnecessary personal data from logs.
- Document privacy notice and internal handling procedure.
- Avoid automatic destructive purging without approved policy and backup verification.

## Permissions

- privacy-settings.view
- privacy-settings.manage
- sensitive-data.view
- sensitive-data.export
- records.archive
- records.dispose

## Tests

- Masking
- Export restrictions
- Retention-rule validation
- Protected-record deletion denial
- Archive behavior
- Log redaction
- Authorization

## Acceptance Criteria

1. Sensitive data is classified.
2. Masking and export restrictions work.
3. Financial and tax records cannot be casually deleted.
4. Retention rules are explicit.
5. Logs exclude unnecessary sensitive data.
6. Tests pass.

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
