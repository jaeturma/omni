# WP-07-05 — Automatic Source Posting

## Objective

Automatically create balanced journal entries from posted operational transactions in Phases 3 through 6.

## Read First

- AGENTS.md
- docs/phases/PHASE-07-ACCOUNTING-ENGINE.md
- docs/work-packages/WP-07-04-posting-rules-account-mapping.md

## Scope

Implement automatic posting for:

- posted sales invoices
- customer payments and withholding
- posted supplier invoices
- supplier payments
- operating expenses
- cash receipts and disbursements
- fund transfers
- petty-cash activity
- inventory purchase receipts
- inventory sales issues
- inventory adjustments
- warehouse transfer rules where accounting effect is required
- physical-count gains and losses

## Functional Requirements

- Post each source exactly once.
- Use source date and fiscal-period rules.
- Resolve account mappings deterministically.
- Create balanced journal entries transactionally.
- Fail safely without partially posting source or journal records.
- Store source-to-journal reference.
- Provide a controlled retry for failed postings.
- Record failure reason.
- Prevent duplicate retries.
- Preserve gross sales, discounts, withholding, cash, receivable, payable, inventory, and cost-of-sales components separately.
- Do not silently post when required mappings are missing.

## Example Posting Patterns

### Sales invoice

- Debit Accounts Receivable
- Credit Sales
- Debit Sales Discounts, when applicable

### Inventory sale issue

- Debit Cost of Sales
- Credit Inventory

### Customer payment

- Debit Cash or Bank
- Debit Creditable Withholding Tax, when applicable
- Credit Accounts Receivable

### Supplier invoice

- Debit Inventory, Expense, or Asset account
- Credit Accounts Payable

### Supplier payment

- Debit Accounts Payable
- Credit Cash or Bank
- Credit Withholding Tax Payable, when applicable

## Permissions

- source-posting.view
- source-posting.retry
- source-posting.rebuild-link
- source-posting.view-errors

## Tests

- Each supported source type
- Duplicate-posting prevention
- Missing mapping failure
- Balanced entry creation
- Posting rollback on error
- Retry behavior
- Gross and withholding separation
- Inventory and cost-of-sales posting
- Authorization
- Concurrency safety

## Acceptance Criteria

1. Supported operational transactions post exactly once.
2. Journal entries are balanced.
3. Source and journal records remain linked.
4. Failures are visible and retryable.
5. No partial posting occurs.
6. Tests and fresh migrations pass.

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
