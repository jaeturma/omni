# WP-01-01 — Business Profile

## Objective
Create the single-business profile used throughout Omni Mini-ERP.

## Read First
- AGENTS.md
- docs/PROJECT_CONTEXT.md
- docs/DEVELOPMENT_RULES.md
- docs/DATA_MODEL.md
- docs/phases/PHASE-01-BUSINESS-FOUNDATION.md

## Dependencies
- Phase 0 completed

## Scope
Create a business profile module storing registered business identity, address, contact details, regional settings, and optional logo.

## Required Data
- registered_business_name
- trade_name
- proprietor_name
- tin
- branch_code
- rdo_code
- registration_date
- business_start_date
- complete registered address
- email
- phone
- website
- default_currency
- timezone
- fiscal_year_start_month
- logo_path
- active

## Functional Requirements
- Support one active business profile.
- Allow authorized create and update.
- Prevent duplicate active profiles.
- Display the configured business name in the application shell.
- Validate optional logo uploads.
- Record creator and updater where practical.

## Backend Requirements
- Migration and model
- Form Requests
- Controller and policy
- Routes
- Optional logo storage

## Frontend Requirements
Blade screens for viewing and editing, grouped into registration identity, address, contacts, regional settings, and branding.

## Permissions
- business-profile.view
- business-profile.update

## Tests
- Authorized view, create, and update
- Unauthorized denial
- Required-field validation
- Reasonable TIN and branch-code validation
- Only one active profile
- Invalid logo rejection

## Out of Scope
Multiple companies, branch accounting, customers, suppliers, tax calculations, sales, inventory, and accounting.

## Acceptance Criteria
1. Exactly one active business profile is supported.
2. Form Request validation and policy authorization are enforced.
3. The shell can display the configured business name.
4. Logo upload is optional and validated.
5. Tests and fresh migrations pass.
6. No unrelated module is implemented.
