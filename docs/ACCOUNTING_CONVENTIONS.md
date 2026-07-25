# Accounting Conventions

## Classification and normal balances

Accounts use the classes `asset`, `liability`, `owner_equity`, `income`, `cost_of_sales`, `expense`, `other_income`, and `other_expense`.

Assets, cost of sales, expenses, and other expenses normally carry debit balances. Liabilities, owner equity, income, and other income normally carry credit balances. Owner drawings are a debit-balance contra-equity account and must not be classified as an operating expense. Owner capital and retained earnings carry credit balances.

## Journal lifecycle and sources

Journal entries use explicit types for opening, sales, collection, purchase, supplier payment, expense, cash receipt, cash disbursement, transfer, inventory, adjustment, reversal, and closing activity.

The lifecycle is `draft` to `posted`, with a posted entry corrected only through a new reversal entry. Posted entries are immutable and must never be hard-deleted. Each automatic entry retains one supported operational source reference, and database uniqueness must prevent that source from posting more than once.

## Dates and periods

The document date preserves the source document's date. The posting date is the accounting date and must fall inside the selected open fiscal period. Closed and locked periods reject posting.

## Precision and balancing

Journal amounts use `decimal(19, 4)` and decimal-safe server-side arithmetic. Values are rounded half up to four decimal places before persistence. Total debits must equal total credits exactly at stored precision; the balancing tolerance is `0.0000`.

## Scope boundary

These conventions do not create accounts, journal entries, posting rules, ledger records, trial balances, period-close transactions, financial statements, or tax returns. Those require separate approved work packages.
