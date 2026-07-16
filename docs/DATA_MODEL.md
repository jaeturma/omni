# Data Model Conventions

This document defines conventions only. It does not authorize creation of all planned tables.

## Core Conventions

- Primary keys: bigint
- Foreign keys: unsigned bigint columns named with the singular related model plus `_id`
- Money: decimal(19, 4)
- Quantity: decimal(19, 4)
- Rates and percentages: decimal(9, 6)
- Accounting dates: date
- System events: timestamps
- Currency: PHP by default
- Timezone: Asia/Manila

Foreign keys should use Laravel's `foreignId()->constrained()` convention where the table name can be inferred. Each relationship must choose an explicit delete behavior appropriate to the record lifecycle; financial and posted records must not cascade-delete.

## Record Status

Transactional records should use explicit statuses when appropriate:

- draft
- posted
- partially_paid
- paid
- voided
- reversed
- cancelled

Not every table requires every status.

## Posting Information

Posted financial documents should normally preserve:

- posted_at
- posted_by
- posting_reference
- voided_at
- voided_by
- void_reason
- reversed_at
- reversed_by
- reversal_reference

Add only the fields required by the active module.

## Document Numbering

Controlled documents should use a document sequence mechanism rather than calculating the next number from the current maximum.

The resulting document number must also have a database unique constraint within its approved scope, such as document type, fiscal year, or business entity. Sequence allocation must be concurrency-safe and will be defined by its approved work package.

Examples:

- Sales invoice
- Collection receipt
- Purchase invoice
- Supplier payment
- Expense voucher
- Journal entry
- Inventory adjustment

Sequences should support:

- document type
- prefix
- current number
- padding
- fiscal year or period reset rule
- active status

## Business Scoping

The initial application may operate for one business entity, but records should avoid assumptions that make future business scoping impossible.

Do not add `business_id` to every table until the approved architecture work package decides whether multi-business support is required.

## Financial Transactions

Financial transactions must retain:

- accounting date
- source document date
- document number
- counterparty
- currency
- gross amount
- discounts
- withholding deductions
- net amount
- status
- audit information

## Journal Entries

Every posted journal entry must:

- have at least two lines
- have total debits equal total credits
- use decimal-safe calculations
- reference its source transaction
- prevent silent modification after posting
- be reversed rather than deleted

## Inventory

Inventory movements should be append-only after posting.

Possible movement types:

- purchase receipt
- sale issue
- customer return
- supplier return
- adjustment in
- adjustment out
- transfer in
- transfer out
- opening balance

Weighted-average costing is the initial recommended costing method, subject to a later approved inventory work package.
