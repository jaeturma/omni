# Audit and Document Status Conventions

This document defines conventions only. It does not authorize audit, accounting, or business tables.

## Financial Document Statuses

- `draft`: Editable and not posted to accounting or inventory. A draft may be posted or cancelled.
- `posted`: Validated and committed together with all required journal entries, stock movements, and posting metadata in one database transaction. It is immutable after that transaction succeeds.
- `voided`: A posted source document has been invalidated with a required reason and compensating records. The original record and number remain visible and unchanged.
- `reversed`: A posted financial effect has been negated by a new, linked reversal dated in an open accounting period. The original record remains posted and immutable.
- `cancelled`: A draft was abandoned before posting. It has no accounting or inventory effect and cannot be posted later.

Allowed lifecycle transitions are `draft -> posted`, `draft -> cancelled`, and `posted -> voided` or `posted -> reversed` when the document type supports that correction. A posted record never returns to draft. Payment states such as `partially_paid` and `paid` describe settlement and do not replace this lifecycle.

## Authorization

Until a roles work package defines additional mappings, only the owner account may post, void, reverse, or reopen an accounting period. Future access must remain default-deny and be enforced through separate policy abilities named `post`, `void`, `reverse`, and `reopen`.

Reopening applies to an accounting period, not to an individual posted document. It requires an explicit reason and must not make posted records editable.

## Posting and Immutability

Posting must run in one database transaction. The operation must validate the document, allocate or preserve its controlled number, create every required accounting or inventory effect, record audit information, and set the final status atomically.

Once posted, business fields, lines, amounts, dates, counterparties, document numbers, and posting references cannot be updated or deleted. Corrections use voiding, reversal, or a separately posted adjustment. Display-only annotations may be added only when they do not alter the financial meaning and are audited.

Minimum posting metadata is:

- `posted_at`
- `posted_by` as a user foreign key
- `posting_reference`
- the accounting period or equivalent period reference

## Voiding and Reversal

Voiding or reversing requires confirmation, authorization, an open correction period, and a non-empty reason. The operation must be atomic and create any compensating journal entries or stock movements required by the source module.

Minimum voiding metadata is `voided_at`, `voided_by`, `void_reason`, and a compensation reference. Minimum reversal metadata is `reversed_at`, `reversed_by`, `reversal_reason`, and `reversal_reference`.

A reversal record must point to the original record, and the original must point to the reversal. Reversals receive their own accounting date and controlled reference. Neither operation deletes or overwrites the original transaction.

## Audit Events

Audit event names use lowercase dot-separated names in the form `<subject>.<past-tense-action>`, for example `sales_invoice.posted`, `journal_entry.reversed`, and `accounting_period.reopened`.

Material events include draft creation and updates, posting, cancellation, voiding, reversal, period closing or reopening, and changes to financial configuration. Each event records the event name, subject type and identifier, actor user identifier, occurrence timestamp, and reason when the action requires one.

Before-and-after values are required for material updates to drafts and configuration. Creation events store the resulting values. Posting events store or reference the immutable posted snapshot. Void, reversal, cancel, and reopen events store the reason and linkage metadata rather than pretending the original values changed. Secrets and authentication credentials must never be captured.

## Journal Source Linkage

Each source document that posts to accounting must keep a foreign-key reference to its resulting journal entry or posting batch, with a uniqueness constraint where posting is one-to-one. Journal entries must retain the immutable source type, source identifier, source document number, and posting reference needed to trace back to the source. Reversal journal entries must also reference the journal entry they reverse.

The module work package must prevent duplicate posting with a database constraint or transaction-safe idempotency rule; checking application state alone is insufficient.

## Audit Implementation Decision

Omni Mini-ERP will use application-owned audit tables in a future approved work package. No activity-log package is selected or installed. Native tables keep financial event semantics, retention, redaction, and transaction boundaries explicit without adding a general-purpose dependency before the required schema is known.
