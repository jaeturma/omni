# WP-10-06 — Performance and Database Optimization

## Objective

Validate and optimize common production workflows without premature complexity.

## Read First

- AGENTS.md
- docs/phases/PHASE-10-PRODUCTION-READINESS-AND-HARDENING.md
- docs/reviews/PHASE-03-VALIDATION.md
- docs/reviews/PHASE-04-VALIDATION.md
- docs/reviews/PHASE-05-VALIDATION.md
- docs/reviews/PHASE-06-VALIDATION.md
- docs/reviews/PHASE-07-VALIDATION.md
- docs/reviews/PHASE-08-VALIDATION.md
- docs/reviews/PHASE-09-VALIDATION.md

## Scope

Profile and optimize:

- Dashboard loading
- Customer and supplier listings
- Product search
- Sales invoice listing and posting
- Supplier invoice listing and posting
- Receivable and payable aging
- Stock ledger
- General ledger
- Trial balance
- Financial statements
- Tax reconciliation
- Large CSV exports
- Attachment listing
- Audit-log listing

## Functional Requirements

- Identify N+1 queries.
- Add indexes only where supported by query evidence.
- Use pagination.
- Use eager loading appropriately.
- Use chunking or streaming for large exports.
- Avoid loading full ledgers into memory.
- Cache stable reference data only with clear invalidation.
- Document baseline and improved measurements.
- Do not introduce Redis unless evidence and deployment capacity justify it.
- Do not denormalize financial source-of-truth records without explicit review.

## Tests and Benchmarks

- Query-count assertions where practical
- Large-list pagination
- Large export memory behavior
- Report execution with representative data
- Posting transaction response
- Index verification
- Cache invalidation if implemented

## Acceptance Criteria

1. Critical workflows have documented baselines.
2. N+1 problems are resolved.
3. Index changes are evidence-based.
4. Large reports and exports are memory-safe.
5. No unnecessary infrastructure is introduced.
6. Tests and benchmarks pass.

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
