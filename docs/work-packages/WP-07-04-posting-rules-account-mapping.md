# WP-07-04 — Posting Rules and Account Mapping

## Objective

Create configurable account mappings that translate operational transaction types into debit and credit accounts.

## Read First

- AGENTS.md
- docs/phases/PHASE-07-ACCOUNTING-ENGINE.md
- docs/work-packages/WP-07-03-journal-entries-lines.md

## Scope

Create mapping records for:

- sales
- sales returns and discounts
- customer collections
- customer withholding credits
- purchases
- supplier payments
- operating expenses
- cash receipts
- cash disbursements
- bank charges
- transfers
- inventory receipts
- inventory issues
- inventory adjustments
- physical-count gains and losses
- owner capital
- owner drawings

## Mapping Dimensions

Mappings may vary by:

- source type
- product category
- service category
- expense category
- customer type
- supplier type
- financial account
- tax code
- warehouse, only if justified

Keep fallback rules explicit.

## Functional Requirements

- Define required debit and credit roles per source type.
- Validate that mapped accounts are active and postable.
- Prevent ambiguous overlapping mappings.
- Provide fallback-account rules.
- Preview posting outcome without creating a journal.
- Support effective dates where account treatment may change.
- Keep mapping changes auditable.
- Do not post source transactions in this work package.

## Permissions

- posting-rules.view
- posting-rules.create
- posting-rules.update
- posting-rules.activate
- posting-rules.deactivate
- posting-rules.preview

## Tests

- Mapping resolution
- Specific versus fallback mapping
- Ambiguity prevention
- Inactive-account rejection
- Effective-date behavior
- Preview without journal creation
- Authorization

## Acceptance Criteria

1. Source types resolve to valid debit and credit accounts.
2. Ambiguous mappings are blocked.
3. Fallback behavior is explicit.
4. Preview is non-destructive.
5. Tests and fresh migrations pass.

## General Requirements

- Follow `AGENTS.md`.
- Use Laravel 13, Blade, Form Requests, policies, services/actions, and focused Pest tests.
- Use decimal-safe calculations and balanced-entry validation.
- Use database transactions and row locking for posting, reversal, closing, and reopening.
- Never hard-delete posted journal entries or ledger-affecting source links.
- Preserve source transaction references, posting metadata, and user attribution.
- Do not implement final financial statements, BIR return filing, payroll, fixed-asset depreciation, or consolidation.
- Do not modify unrelated modules.

## Completion Report

Report only:

1. Files changed
2. Commands and tests run
3. Acceptance criteria result
4. Remaining issues requiring owner action
