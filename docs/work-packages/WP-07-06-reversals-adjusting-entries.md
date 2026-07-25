# WP-07-06 — Reversals and Adjusting Entries

## Objective

Implement controlled reversals, correcting entries, opening entries, and manual adjusting entries.

## Read First

- AGENTS.md
- docs/phases/PHASE-07-ACCOUNTING-ENGINE.md
- docs/work-packages/WP-07-05-automatic-source-posting.md

## Scope

Support:

- reversal of posted automatic entries
- reversal of manual entries
- correcting journal entries
- opening journal entries
- period-end adjusting entries
- optional auto-reversing entries for the next open period

## Functional Requirements

- Never modify original posted lines.
- Create a linked reversal entry with opposite debit and credit amounts.
- Require reason and authorization.
- Respect open-period rules.
- Prevent multiple active reversals of the same entry.
- Preserve original and reversal references.
- Support correction by reversal plus replacement.
- Allow auto-reversal date only in an open future period.
- Do not implement closing-to-retained-earnings yet unless required by WP-07-09.

## Permissions

- journals.reverse
- journals.adjust
- journals.opening-entry
- journals.auto-reverse

## Tests

- Full reversal
- Duplicate-reversal prevention
- Closed-period rejection
- Correction workflow
- Auto-reversal
- Source-linked reversal
- Authorization
- Balance integrity

## Acceptance Criteria

1. Reversals preserve the original entry.
2. Corrections are fully traceable.
3. Duplicate reversal is blocked.
4. Period and permission rules work.
5. Tests and fresh migrations pass.

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
