# WP-03-09 — Sales Attachments and Traceability

## Objective

Add secure attachments and end-to-end source traceability across all Phase 3 records.

## Read First

- AGENTS.md
- docs/phases/PHASE-03-SALES-AND-COLLECTIONS.md

## Scope

Support private attachments for quotations, orders, deliveries, invoices, payments, and government deductions.

## Required Metadata

- document type
- original and stored filename
- MIME type and size
- file hash
- document date
- reference number
- uploader and timestamp
- related sales record
- notes

## Functional Requirements

- Use private storage.
- Validate type and size.
- Authorize upload, download, and deletion.
- Prevent silent replacement.
- Protect attachments linked to posted records.
- Display source links from quotation through payment.
- Preserve file hashes.
- Avoid unnecessary package installation.

## Permissions

- sales-attachments.view
- sales-attachments.upload
- sales-attachments.delete

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
4. Sales source traceability is visible.
5. Tests pass.

## General Requirements

- Follow `AGENTS.md`.
- Use Laravel 13, Blade, Form Requests, policies, and focused Pest tests.
- Use decimal-safe server-side calculations.
- Use database transactions for posting and allocations.
- Use document sequences where applicable.
- Never hard-delete posted financial records.
- Do not implement general-ledger entries, inventory costing, financial statements, or BIR return filing.
- Do not modify unrelated modules.

## Completion Report

Report only:

1. Files changed
2. Commands and tests run
3. Acceptance criteria result
4. Remaining issues requiring owner action
