# WP-01-05 — Roles and User Administration

## Objective
Create a minimal role, permission, and user-administration system.

## Read First
- AGENTS.md
- docs/PROJECT_CONTEXT.md
- docs/DEVELOPMENT_RULES.md
- docs/phases/PHASE-01-BUSINESS-FOUNDATION.md

## Dependencies
- Authentication baseline completed
- WP-01-01 completed

## Initial Roles
- Administrator
- Owner
- Bookkeeper
- Encoder
- Viewer

## Initial Permission Groups
Business profile, tax profile, fiscal periods, document sequences, users, roles, and system settings.

## Package Decision
Prefer `spatie/laravel-permission` if no equivalent system exists. Confirm first, document why, and install only what is required.

## Functional Requirements
- List, create, and update users.
- Assign roles.
- Activate and deactivate users.
- Prevent deactivation of the last active administrator.
- Prevent administrative self-lockout.
- Keep passwords hashed.
- Support a secure password-reset process.

## Frontend Requirements
Blade screens for user listing, create/edit, role assignment, activation/deactivation, and a read-only role-permission matrix.

## Tests
- Administrator management
- Unauthorized denial
- Role assignment
- Phase 1 permission enforcement
- Inactive login rejection
- Last-administrator protection
- Password hashing
- Self-lockout protection

## Out of Scope
2FA, social login, API tokens, HR records, employee management, and advanced approvals.

## Acceptance Criteria
1. Initial roles and permissions exist.
2. Phase 1 screens are protected.
3. Users are managed safely.
4. Lockout protections exist.
5. Tests and fresh migrations pass.
6. No HR module is implemented.
