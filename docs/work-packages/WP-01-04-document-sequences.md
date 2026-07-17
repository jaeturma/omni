# WP-01-04 — Document Sequences

## Objective
Create concurrency-safe document numbering for future modules.

## Read First
- AGENTS.md
- docs/PROJECT_CONTEXT.md
- docs/DEVELOPMENT_RULES.md
- docs/DATA_MODEL.md
- docs/phases/PHASE-01-BUSINESS-FOUNDATION.md

## Dependencies
- WP-01-01 completed
- WP-01-03 completed

## Initial Sequence Types
- sales_invoice
- collection_receipt
- purchase_invoice
- supplier_payment
- expense_voucher
- inventory_adjustment
- journal_entry

## Requirements
- Configurable prefix, suffix, current number, padding, reset rule, and fiscal-year linkage.
- Preview without consuming a number.
- Transaction-safe issuance with row locking.
- Never use only `MAX(document_number)`.
- Preserve issuance history.
- Prevent duplicates with a unique constraint.

## Suggested Tables
- document_sequences
- document_number_reservations

## Example Formats
- SI-2026-000001
- CR-2026-000001
- PI-2026-000001
- SP-2026-000001
- EXP-2026-000001
- IA-2026-000001
- JV-2026-000001

## Permissions
- document-sequences.view
- document-sequences.manage
- document-sequences.issue

## Tests
- Formatting and padding
- Fiscal-year formatting
- Sequential issuance
- Duplicate prevention
- Inactive-sequence rejection
- Preview does not consume
- Authorization
- Repeated transaction-safe issuance

## Acceptance Criteria
1. Sequence definitions are configurable.
2. Issuance is transactional and duplicate-safe.
3. Preview does not consume a number.
4. History is preserved.
5. Tests and fresh migrations pass.
6. No transactional module is implemented.
