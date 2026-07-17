# WP-04-01 — Purchasing Settings and Workflow

## Objective

Establish shared purchasing statuses, numbering links, approval conventions, terms, and calculation rules before transactional purchasing modules are created.

## Read First

- AGENTS.md
- docs/PROJECT_CONTEXT.md
- docs/DEVELOPMENT_RULES.md
- docs/DATA_MODEL.md
- docs/phases/PHASE-04-PURCHASING-AND-EXPENSES.md

## Scope

- Define request, canvass, purchase-order, receiving, supplier-invoice, supplier-payment, expense, and allocation statuses.
- Define allowed status transitions.
- Define shared discount, freight, withholding, and total rules.
- Confirm document-sequence codes.
- Define procurement references and approval metadata.
- Create or seed Phase 4 permissions.
- Document direct-purchase and purchase-order workflows.
- Create shared enums or support classes only when clearly reusable.

## Required Rules

- Amounts are calculated server-side.
- Posted supplier invoices and payments are immutable.
- Gross purchase, discounts, freight, withholding, cash paid, and balance due remain separate.
- Withholding is not a purchase discount.
- Document numbers are consumed only at approved issuance or posting points.
- Voiding requires reason, user, and timestamp.
- Receiving records do not calculate inventory cost in this phase.

## Tests

- Status-transition validation
- Decimal calculation rules
- Permission seeding
- Document-sequence mapping
- No transactional records created

## Acceptance Criteria

1. Purchasing workflow conventions are documented and implemented centrally.
2. Status transitions are explicit.
3. Permissions exist.
4. Calculation rules are tested.
5. No purchase request, order, supplier invoice, payment, stock, or journal transaction is implemented.

## General Requirements

- Follow `AGENTS.md`.
- Use Laravel 13, Blade, Form Requests, policies, and focused Pest tests.
- Use decimal-safe server-side calculations.
- Use database transactions for posting, allocations, and status changes.
- Use document sequences where applicable.
- Never hard-delete posted financial records.
- Preserve gross purchases, discounts, withholding, payments, and balances separately.
- Do not implement general-ledger entries, inventory costing, financial statements, or BIR return filing.
- Do not modify unrelated modules.

## Completion Report

Report only:

1. Files changed
2. Commands and tests run
3. Acceptance criteria result
4. Remaining issues requiring owner action
