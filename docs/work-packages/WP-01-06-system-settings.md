# WP-01-06 — System Settings

## Objective
Create controlled application-wide settings that do not belong to business or tax profiles.

## Read First
- AGENTS.md
- docs/PROJECT_CONTEXT.md
- docs/DEVELOPMENT_RULES.md
- docs/phases/PHASE-01-BUSINESS-FOUNDATION.md

## Dependencies
- WP-01-01 completed
- WP-01-05 completed

## Initial Settings
- application_display_name
- default_date_format
- default_time_format
- default_currency
- currency_symbol
- decimal_places
- quantity_decimal_places
- default_paper_size
- records_per_page
- low_stock_default_threshold
- attachment_max_size
- backup_path_label
- maintenance_contact_name
- maintenance_contact_email

## Design Requirements
- Controlled settings registry.
- Typed validation.
- No arbitrary user-defined keys.
- No secrets.
- Safe defaults.
- Central typed settings service.
- Clear cache invalidation if caching is used.

## Suggested Table
- system_settings

## Permissions
- system-settings.view
- system-settings.update

## Tests
- Authorized access
- Unauthorized denial
- Type and range validation
- Safe defaults
- Settings service retrieval
- Secrets cannot be created
- Cache invalidation if applicable

## Out of Scope
SMTP credentials, database credentials, API keys, payment credentials, backup execution, theme builder, and per-user preferences.

## Acceptance Criteria
1. Settings are controlled and typed.
2. Secrets are excluded.
3. Authorization and validation are enforced.
4. Settings are retrieved through one service.
5. Tests and fresh migrations pass.
6. No unrelated module is implemented.
