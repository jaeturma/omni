# WP-06-07 — Physical Counts and Reconciliation

## Objective

Record physical inventory counts and reconcile variances through approved inventory movements.

## Read First

- AGENTS.md
- docs/phases/PHASE-06-INVENTORY-AND-STOCK-CONTROL.md
- docs/work-packages/WP-06-06-warehouse-transfers.md

## Scope

Create count sessions and lines containing:

- count session number
- warehouse
- count date
- cutoff timestamp
- counted_by
- reviewed_by
- product
- system quantity snapshot
- counted quantity
- variance quantity
- unit cost snapshot
- variance value
- explanation
- draft, counting, submitted, approved, posted, and voided statuses

## Functional Requirements

- Freeze a system-quantity snapshot at count cutoff.
- Allow blind count mode.
- Calculate variances server-side.
- Require review and approval.
- Post gains or losses as physical-count movements.
- Prevent duplicate posting.
- Preserve count and cost snapshots.
- Support recount before posting.
- Do not overwrite existing movements.
- Do not create journal entries.

## Permissions

- physical-counts.view
- physical-counts.create
- physical-counts.count
- physical-counts.review
- physical-counts.approve
- physical-counts.post
- physical-counts.void

## Tests

- Snapshot accuracy
- Blind count
- Variance calculation
- Recount
- Approval and posting
- Duplicate prevention
- Reversal
- Authorization

## Acceptance Criteria

1. Counts preserve a reliable cutoff snapshot.
2. Variances are accurate.
3. Approved variances create movements.
4. Existing movement history remains intact.
5. Tests and fresh migrations pass.

## General Requirements

- Follow `AGENTS.md`.
- Use Laravel 13, Blade, Form Requests, policies, and focused Pest tests.
- Use decimal-safe server-side calculations.
- Use database transactions and row locking for stock movements and costing.
- Use document sequences where applicable.
- Never hard-delete posted inventory movements.
- Preserve source document references, costing details, and user attribution.
- Do not implement general-ledger entries, financial statements, tax return filing, payroll, or fixed-asset depreciation.
- Do not modify unrelated modules.

## Completion Report

Report only:

1. Files changed
2. Commands and tests run
3. Acceptance criteria result
4. Remaining issues requiring owner action
