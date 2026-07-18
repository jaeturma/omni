# Cash and Bank Conventions

## Lifecycle

Cash and bank transactions begin as `draft`, may be `posted`, and may then be `voided`. Posted records are immutable. Voiding must retain a reason, user, and timestamp; it must not silently edit or hard-delete the posted record.

Operational balances are derived only from posted, non-voided transaction records. Reconciliation records may classify or match activity but must never change a transaction amount.

## Source-linked workflows

Customer receipts, supplier payments, and expense payments retain their originating record and user attribution. A later operational work package may materialize the cash-side record, but it must not duplicate or change the source document's gross amount, withholding, deductions, or net settlement.

## Manual workflows

Deposits, withdrawals, adjustments, and opening balances require an explicit account, date, amount, reference, business explanation, and creator. Adjustments are controlled corrections, not substitutes for editing posted records.

## Transfers and petty cash

A transfer is one atomic operation represented by linked `transfer_out` and `transfer_in` records with equal amounts and distinct source and destination accounts. Partial transfer posting is prohibited.

Petty-cash releases and replenishments retain their custodian or source reference. Replenishment does not erase the releases it reimburses.

## Reconciliation

Reconciliation states are `unreconciled`, `matched`, `reconciled`, and `disputed`. Matching and reconciliation preserve transaction amount, date, source reference, and posting audit fields.

## Phase boundary

These conventions do not create financial accounts, cash transactions, transfers, petty-cash records, statement imports, reconciliations, journal entries, financial statements, tax returns, payroll, or inventory costing. No general-ledger posting occurs in Phase 5.
