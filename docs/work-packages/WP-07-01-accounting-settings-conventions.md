# WP-07-01 — Accounting Settings and Conventions

## Objective

Establish accounting principles, account types, normal balances, journal statuses, source types, and permissions before creating ledger records.

## Read First

- AGENTS.md
- docs/PROJECT_CONTEXT.md
- docs/DEVELOPMENT_RULES.md
- docs/DATA_MODEL.md
- docs/phases/PHASE-07-ACCOUNTING-ENGINE.md

## Scope

- Define account classes and account types.
- Define normal debit or credit balances.
- Define journal-entry types and statuses.
- Define source-transaction types.
- Define posting-date and document-date rules.
- Define rounding and balancing tolerance.
- Define retained-earnings and owner-equity conventions.
- Create or seed Phase 7 permissions.
- Create shared enums or support classes only when clearly reusable.

## Initial Account Classes

- asset
- liability
- owner_equity
- income
- cost_of_sales
- expense
- other_income
- other_expense

## Initial Journal Types

- opening
- sales
- collection
- purchase
- supplier_payment
- expense
- cash_receipt
- cash_disbursement
- transfer
- inventory
- adjustment
- reversal
- closing

## Required Rules

- Total debits must equal total credits.
- Posted entries are immutable.
- Every automatic entry references one source transaction.
- One source posting may not be duplicated.
- Reversals create new entries.
- Posting dates must belong to an open fiscal period.
- Closed or locked periods reject new postings.
- Amounts use `decimal(19, 4)`.
- No floating-point arithmetic.
- No final financial statements are implemented in this work package.

## Tests

- Account-class and normal-balance rules
- Journal-type validation
- Posting-period validation
- Balancing tolerance
- Permission seeding
- No journal entry created

## Acceptance Criteria

1. Accounting conventions are centralized.
2. Account and journal types are explicit.
3. Posting and balancing rules are documented.
4. Permissions exist.
5. No ledger transaction is implemented.

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
