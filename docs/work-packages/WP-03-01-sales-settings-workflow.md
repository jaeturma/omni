# WP-03-01 — Sales Settings and Workflow Conventions

## Objective

Establish shared sales statuses, numbering links, terms, calculation conventions, and permissions before transactional sales modules are created.

## Read First

- AGENTS.md
- docs/PROJECT_CONTEXT.md
- docs/DEVELOPMENT_RULES.md
- docs/DATA_MODEL.md
- docs/TAX_PROFILE.md
- docs/phases/PHASE-03-SALES-AND-COLLECTIONS.md

## Scope

- Define quotation, order, delivery, invoice, payment, and allocation statuses.
- Define allowed status transitions.
- Define shared discount and amount calculation rules.
- Confirm document-sequence codes.
- Create payment-term records only if Phase 2 did not already provide them.
- Create or seed Phase 3 permissions.
- Document direct-sale and order-based workflows.
- Create shared enums or support classes only when clearly reusable.

## Required Rules

- Amounts are calculated server-side.
- Posted invoices and payments are immutable.
- Gross sales, discounts, withholding, cash received, and balance due remain separate.
- Withholding is not a sales discount.
- Document numbers are consumed only at the approved issuance or posting point.
- Voiding requires reason, user, and timestamp.

## Tests

- Status-transition validation
- Decimal calculation rules
- Permission seeding
- Document-sequence mapping
- No transactional records created

## Acceptance Criteria

1. Sales workflow conventions are documented and implemented centrally.
2. Status transitions are explicit.
3. Permissions exist.
4. Calculation rules are tested.
5. No quotation, invoice, payment, stock, or journal transaction is implemented.

## General Requirements

- Follow `AGENTS.md`.
- Use Laravel 13, Blade, Form Requests, policies, and focused Pest tests.
- Use decimal-safe server-side calculations.
- Use database transactions for posting and allocations.
- Use document sequences where applicable.
- Never hard-delete posted financial records.
- Do not implement general-ledger entries, inventory costing, financial statements, or BIR return filing.
- Do not modify unrelated modules.

## Completion Report

Report only:

1. Files changed
2. Commands and tests run
3. Acceptance criteria result
4. Remaining issues requiring owner action
