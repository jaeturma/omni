# Omni Mini-ERP Project Context

## Project

Omni Mini-ERP is a simple bookkeeping, inventory, sales, purchasing, collection, payment, financial reporting, and tax-preparation system for a Philippine sole proprietorship.

The project must remain simple enough for daily owner-operated use while maintaining a correct foundation for accounting records and future growth.

## Existing Environment

- Laravel 13 is already installed.
- Blade is the selected frontend.
- Local URL: https://omni.app
- Database: MySQL 8
- Development environment: Laragon on Windows
- Version control: Git
- AI development assistant: Codex in VS Code

## Business Activities

- ICT equipment reselling
- ICT accessories reselling
- Computer repair and maintenance
- Network installation and configuration
- CCTV and related ICT services
- Office supply reselling
- School supply reselling
- Sales to private customers
- Sales to government agencies, including DepEd

## Taxpayer Profile

- Legal form: Sole proprietorship
- Registration: Non-VAT
- Business operations started: May 2026
- First taxable quarter: Second Quarter of 2026
- Initial percentage tax return: BIR Form 2551Q
- Tax rates and tax obligations must remain configurable.
- The system prepares tax worksheets and reports but does not directly file returns with BIR.
- Final tax treatment must remain reviewable against the Certificate of Registration and current BIR rules.

## Development Principles

- Modular monolith
- Standard Laravel conventions
- Server-rendered Blade interfaces
- Minimal JavaScript
- Mobile-responsive forms and reports
- Simple transaction entry
- Double-entry accounting foundation
- Immutable posted transactions
- Complete audit trail for material financial activity
- Configurable tax rules
- No premature abstractions
- No unnecessary packages
- One approved work package at a time

## Planned Modules

1. Environment and application baseline
2. Authentication and user access
3. Business and tax profile
4. Fiscal periods and document sequences
5. Customers and suppliers
6. Product, service, category, and unit master data
7. Sales invoices and customer collections
8. Purchases, supplier invoices, and supplier payments
9. Operating expenses
10. Cash and bank accounts
11. Inventory and stock movements
12. Chart of accounts and automatic journal posting
13. General ledger and trial balance
14. Financial statements
15. BIR preparation worksheets
16. Audit logs and document attachments

## Current Development Rule

Implement only the active work package. Do not begin future modules, create speculative tables, or install packages not required by the current scope.
