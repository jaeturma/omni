# WP-01-02 — Tax Profile

## Objective
Create a configurable tax registration profile without implementing tax calculations or filing.

## Read First
- AGENTS.md
- docs/PROJECT_CONTEXT.md
- docs/DEVELOPMENT_RULES.md
- docs/TAX_PROFILE.md
- docs/phases/PHASE-01-BUSINESS-FOUNDATION.md

## Dependencies
- WP-01-01 completed

## Scope
Create a tax profile linked to the active business.

## Required Data
- taxpayer_type
- registration_type
- vat_status
- income_tax_option
- percentage_tax_registered
- percentage_tax_rate
- percentage_tax_effective_from
- percentage_tax_effective_to
- filing_frequency
- registration_start_date
- first_filing_period
- rdo_code
- tin
- branch_code
- registered_books_type
- notes
- active

## Requirements
- One active tax profile per active business.
- Configurable effective-dated tax rates.
- Historical rates retained.
- Overlapping effective dates rejected.
- Applicable BIR forms recorded.
- Display a tax-preparation-only disclaimer.
- Do not calculate 2551Q.

## Suggested Tables
- tax_profiles
- tax_rate_settings
- tax_form_registrations

## Permissions
- tax-profile.view
- tax-profile.update
- tax-rates.manage

## Tests
- Authorized view and update
- Unauthorized denial
- Decimal-safe rates
- Effective-date validation
- Overlap rejection
- Link to active business
- No tax computation

## Acceptance Criteria
1. One active tax profile exists per business.
2. Rates are effective-dated and configurable.
3. Overlapping periods are prevented.
4. BIR forms can be recorded.
5. Authorization and validation are enforced.
6. Tests and fresh migrations pass.
7. No tax calculation is implemented.
