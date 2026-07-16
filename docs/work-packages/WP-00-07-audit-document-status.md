# WP-00-07 — Audit and Document Status Conventions

## Objective

Define the minimum audit and document-status rules that future financial modules must follow.

## Read First

- AGENTS.md
- docs/PROJECT_CONTEXT.md
- docs/DEVELOPMENT_RULES.md
- docs/DATA_MODEL.md

## Dependencies

- WP-00-06 completed

## Scope

- Define draft, posted, voided, reversed, and cancelled meanings.
- Define when posted records become immutable.
- Define minimum posting metadata.
- Define minimum voiding and reversal metadata.
- Define audit-event naming conventions.
- Decide whether native application audit tables or a small package will be used in a future work package.
- Do not install an audit package during this work package unless explicitly approved.

## Required Decisions

Document:

- Which statuses apply to financial documents
- Which roles may post, void, reverse, or reopen
- Required reason fields
- Required timestamps and user references
- Whether before-and-after values are needed
- How source transactions link to journal entries

## Out of Scope

- Implementing accounting tables
- Implementing audit tables
- Installing an activity-log package
- Roles and permissions
- Sales or purchase modules

## Acceptance Criteria

1. Status meanings are unambiguous.
2. Posted-record immutability is documented.
3. Voiding and reversal requirements are documented.
4. Audit-event conventions are documented.
5. No speculative implementation is added.
