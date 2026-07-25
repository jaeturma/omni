# WP-05-01 — Cash and Bank Settings

## Objective

Establish shared account types, transaction types, statuses, reconciliation conventions, and permissions before creating operational cash and bank records.

## Read First

- AGENTS.md
- docs/PROJECT_CONTEXT.md
- docs/DEVELOPMENT_RULES.md
- docs/DATA_MODEL.md
- docs/phases/PHASE-05-CASH-AND-BANKING.md

## Scope

- Define account types.
- Define cash and bank transaction types.
- Define allowed statuses and transitions.
- Confirm document-sequence codes.
- Define reconciliation states.
- Create or seed Phase 5 permissions.
- Document source-linked and manual cash workflows.
- Create shared enums or support classes only when clearly reusable.

## Initial Account Types

- cash_on_hand
- petty_cash
- bank_checking
- bank_savings
- e_wallet
- clearing_account
- other_cash_equivalent

## Initial Transaction Types

- customer_receipt
- supplier_payment
- expense_payment
- deposit
- withdrawal
- transfer_in
- transfer_out
- petty_cash_release
- petty_cash_replenishment
- adjustment
- opening_balance

## Required Rules

- Posted transactions are immutable.
- Voiding requires reason, user, and timestamp.
- Transfers must create linked source and destination records.
- Reconciliation does not alter transaction amounts.
- Operational account balances are derived from posted transactions.
- No general-ledger posting occurs in this phase.

## Tests

- Status transitions
- Permission seeding
- Transaction-type rules
- No operational transaction created

## Acceptance Criteria

1. Cash and bank conventions are centralized.
2. Account and transaction types are explicit.
3. Permissions exist.
4. Reconciliation states are defined.
5. No account transaction or journal entry is implemented.

## General Requirements

- Follow `AGENTS.md`.
- Use Laravel 13, Blade, Form Requests, policies, and focused Pest tests.
- Use decimal-safe server-side calculations.
- Use database transactions for posting, reconciliation, and balance changes.
- Use document sequences where applicable.
- Never hard-delete posted cash or bank transactions.
- Preserve source document references and user attribution.
- Do not implement general-ledger entries, financial statements, tax return filing, payroll, or inventory costing.
- Do not modify unrelated modules.

## Completion Report

Report only:

1. Files changed
2. Commands and tests run
3. Acceptance criteria result
4. Remaining issues requiring owner action
