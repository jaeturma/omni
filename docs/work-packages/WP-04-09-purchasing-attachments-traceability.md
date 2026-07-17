# WP-04-09 — Purchasing Attachments and Traceability

## Objective

Add secure attachments and end-to-end source traceability across all Phase 4 records.

## Read First

- AGENTS.md
- docs/phases/PHASE-04-PURCHASING-AND-EXPENSES.md

## Scope

Support private attachments for:

- purchase requests
- canvass records
- purchase orders
- receiving records
- supplier invoices
- supplier payments
- operating expenses

## Suggested Document Types

- purchase_request
- supplier_quotation
- abstract_of_canvass
- purchase_order
- delivery_receipt
- inspection_acceptance_report
- supplier_invoice
- official_receipt
- deposit_or_transfer_confirmation
- withholding_certificate
- expense_receipt
- other_supporting_document

## Required Metadata

- document type
- original and stored filename
- MIME type and size
- file hash
- document date
- reference number
- uploader and timestamp
- related purchasing record
- notes

## Functional Requirements

- Use private storage.
- Validate type and size.
- Authorize upload, download, and deletion.
- Prevent silent replacement.
- Protect attachments linked to posted records.
- Display source links from request through payment or expense.
- Preserve file hashes.
- Avoid unnecessary package installation.

## Permissions

- purchasing-attachments.view
- purchasing-attachments.upload
- purchasing-attachments.delete

## Tests

- Private upload and download
- Validation and authorization
- Hash preservation
- Posted-record protection
- Safe deletion
- End-to-end source links

## Acceptance Criteria

1. Attachments are private and authorized.
2. Metadata and hashes are stored.
3. Posted-document protections work.
4. Purchasing source traceability is visible.
5. Tests pass.

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
