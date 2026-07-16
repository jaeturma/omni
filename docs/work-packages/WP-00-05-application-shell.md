# WP-00-05 — Application Shell

## Objective

Create a minimal, reusable Blade application shell for Omni Mini-ERP without implementing business modules.

## Read First

- AGENTS.md
- docs/PROJECT_CONTEXT.md
- docs/DEVELOPMENT_RULES.md

## Dependencies

- WP-00-04 completed

## Scope

- Create the authenticated application layout.
- Add a top navigation bar or compact sidebar.
- Add a dashboard placeholder.
- Add reusable page-title and flash-message patterns.
- Add mobile-responsive navigation.
- Keep the interface clean and minimal.
- Use existing Tailwind and Blade facilities.
- Use Alpine.js only when required for menu toggling.

## Initial Navigation Placeholders

Show disabled or placeholder navigation labels only when useful:

- Dashboard
- Sales
- Purchases
- Expenses
- Inventory
- Customers
- Suppliers
- Accounting
- Tax Reports
- Settings

Do not create module routes or screens unless explicitly required.

## Out of Scope

- Dashboard metrics
- Charts
- Accounting data
- CRUD modules
- Role-based navigation
- UI animation
- Third-party admin themes
- Filament
- Livewire

## Acceptance Criteria

1. Authenticated users see a consistent layout.
2. Navigation works on desktop and mobile.
3. Flash messages are supported.
4. Validation messages remain readable.
5. No large frontend dependency is added.
6. No business module is implemented.
7. Blade components remain minimal and justified.
