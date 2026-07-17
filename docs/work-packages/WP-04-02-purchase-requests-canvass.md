# WP-04-02 — Purchase Requests and Canvass

## Objective

Create internal purchase requests and optional supplier canvass records without creating purchase orders, payables, inventory movements, or journal entries.

## Read First

- AGENTS.md
- docs/phases/PHASE-04-PURCHASING-AND-EXPENSES.md
- docs/work-packages/WP-04-01-purchasing-settings-workflow.md

## Scope

Create purchase-request headers and lines containing:

- request number and date
- requested_by
- needed_by
- purpose
- requesting unit or project reference
- product, service, or free-text item
- description snapshot
- quantity and UOM snapshot
- estimated unit cost
- estimated total
- preferred supplier, optional
- notes
- draft, submitted, approved, rejected, converted, and cancelled statuses

Create optional canvass records containing:

- purchase request reference
- supplier
- quoted amount
- quotation date
- validity date
- delivery terms
- payment terms
- selected flag
- evaluation notes

## Functional Requirements

- Create and edit drafts.
- Submit, approve, reject, and cancel requests.
- Require reasons for rejection or cancellation.
- Record multiple supplier quotations.
- Mark one quotation as selected.
- Prevent multiple selected quotations for the same canvass set.
- Preserve quotation snapshots.
- Prepare safe conversion to a purchase order.
- Do not create payables, inventory movements, or journals.

## Permissions

- purchase-requests.view
- purchase-requests.create
- purchase-requests.update
- purchase-requests.approve
- purchase-requests.cancel
- purchase-canvass.manage

## Tests

- Request CRUD and validation
- Product, service, and free-text lines
- Approval and rejection
- Multiple canvass quotations
- Single selected quotation
- Authorization
- No payable, stock, or journal effects

## Acceptance Criteria

1. Requests support goods and services.
2. Canvass records support multiple suppliers.
3. Selection and status rules work.
4. Historical snapshots are preserved.
5. Tests and fresh migrations pass.

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
