# WP-05-02 — Financial Accounts

## Objective

Create the master records for cash, bank, e-wallet, petty cash, and clearing accounts.

## Read First

- AGENTS.md
- docs/phases/PHASE-05-CASH-AND-BANKING.md
- docs/work-packages/WP-05-01-cash-bank-settings.md

## Scope

Create financial-account records containing:

- account code
- account name
- account type
- bank reference, optional
- branch name, optional
- masked account number
- account holder name
- currency
- opening balance
- opening balance date
- active status
- allow_receipts
- allow_disbursements
- allow_transfers
- allow_reconciliation
- notes

## Functional Requirements

- Support multiple cash, bank, and e-wallet accounts.
- Mask account numbers in UI.
- Restrict full account-number visibility.
- Prevent duplicate active account code.
- Prevent deactivation while unreconciled activity requires attention, unless explicitly handled.
- Opening balances must be auditable.
- Do not store passwords, PINs, API keys, or online-banking credentials.

## Permissions

- financial-accounts.view
- financial-accounts.create
- financial-accounts.update
- financial-accounts.activate
- financial-accounts.deactivate
- financial-accounts.view-sensitive

## Tests

- CRUD and validation
- Account-number masking
- Duplicate-code prevention
- Authorization
- Opening balance
- No secret fields
- Deactivation controls

## Acceptance Criteria

1. All required account types are supported.
2. Sensitive details are masked.
3. Opening balances are traceable.
4. Authorization and validation work.
5. Tests and fresh migrations pass.

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
