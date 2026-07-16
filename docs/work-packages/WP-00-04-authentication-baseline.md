# WP-00-04 — Authentication Baseline

## Objective

Establish a minimal Laravel authentication baseline using Blade without adding roles, permissions, or business modules.

## Read First

- AGENTS.md
- docs/PROJECT_CONTEXT.md
- docs/DEVELOPMENT_RULES.md

## Dependencies

- WP-00-01 completed
- WP-00-02 completed
- WP-00-03 completed

## Scope

- Inspect the existing Laravel installation for authentication support.
- Use the smallest Laravel-supported Blade authentication approach.
- Implement login, logout, password confirmation, and password reset only if supported by the chosen starter approach.
- Protect the application dashboard behind authentication.
- Preserve standard Laravel conventions.
- Add focused authentication tests.

## Out of Scope

- Roles and permissions
- User administration
- Two-factor authentication
- Social login
- API authentication
- Multi-business accounts
- Business profile
- Accounting
- Sales
- Inventory
- Taxation

## Acceptance Criteria

1. A guest can view the login page.
2. A valid user can log in.
3. An invalid login is rejected.
4. An authenticated user can log out.
5. Protected pages redirect guests to login.
6. Authentication tests pass.
7. No role or permission package is introduced.
8. No business module is implemented.
