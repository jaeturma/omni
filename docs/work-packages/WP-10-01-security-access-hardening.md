# WP-10-01 — Security Baseline and Access Hardening

## Objective

Harden authentication, authorization, sessions, sensitive routes, and administrative access for production use.

## Read First

- AGENTS.md
- docs/PROJECT_CONTEXT.md
- docs/DEVELOPMENT_RULES.md
- docs/phases/PHASE-10-PRODUCTION-READINESS-AND-HARDENING.md

## Scope

Review and harden:

- Authentication flows
- Password rules
- Password reset
- Session lifetime
- Session invalidation
- CSRF protection
- Rate limiting
- Login throttling
- Inactive-user blocking
- Role and permission enforcement
- Administrative lockout protection
- Sensitive-data masking
- Private attachment authorization
- Mass-assignment protection
- Route middleware
- Production debug settings
- Secure cookies
- HTTPS enforcement
- Security headers where appropriate

## Functional Requirements

- Prevent inactive users from authenticating.
- Invalidate sessions after password change or deactivation.
- Protect all financial and tax routes with permissions.
- Prevent privilege escalation through request manipulation.
- Mask TINs, bank accounts, and other sensitive values.
- Restrict attachment access by related-record permission.
- Do not expose stack traces in production.
- Require secure cookies in HTTPS production.
- Add rate limits to authentication and sensitive actions.
- Preserve owner access and last-administrator protections.

## Tests

- Guest access denial
- Inactive-user denial
- Permission matrix coverage
- Privilege-escalation attempts
- Session invalidation
- Sensitive-data masking
- Private-file authorization
- Rate limiting
- Production debug safety

## Acceptance Criteria

1. Sensitive routes are permission-protected.
2. Inactive and unauthorized users are blocked.
3. Sessions and password changes are handled securely.
4. Sensitive data is masked.
5. Private files remain private.
6. Production debug exposure is prevented.
7. Tests pass.

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
