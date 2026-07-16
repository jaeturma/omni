# Omni Mini-ERP Codex Instructions

## Mission

Develop a simple Laravel 13 mini-ERP for a Philippine non-VAT sole proprietorship engaged in:

- ICT equipment reselling
- ICT installation and technical services
- Office supply reselling
- School supply reselling
- Sales to private customers
- Sales to government agencies, including DepEd

## Required Stack

- Laravel 13
- PHP 8.3 or newer
- MySQL 8
- Blade
- Tailwind CSS
- Alpine.js only when necessary
- Pest

Do not introduce React, Vue, Inertia, Livewire, Filament, Docker, Redis, Reverb, Horizon, microservices, or other major infrastructure unless a future approved work package explicitly requires them.

## Application Identity

- Local URL: https://omni.app
- Project type: Laravel modular monolith
- Primary interface: Server-rendered Blade
- Primary database: MySQL 8
- Default timezone: Asia/Manila
- Default currency: Philippine Peso (PHP)

## Working Method

1. Work only on the active work package provided by the user.
2. Inspect only files relevant to the active work package.
3. Do not scan the entire repository unless explicitly instructed.
4. Do not implement future modules.
5. Do not create speculative abstractions.
6. Do not modify unrelated files.
7. Prefer standard Laravel conventions.
8. Reuse existing project patterns before creating new ones.
9. Keep progress reports and completion reports concise.
10. Stop after completing the active work package.
11. Do not automatically continue to the next work package.
12. Do not install a package without documenting why it is necessary.

## Before Implementation

For every work package:

1. Read this file.
2. Read only the documents named in the work package.
3. Inspect only the relevant routes, migrations, models, controllers, requests, views, policies, services, and tests.
4. Provide a brief implementation plan of no more than ten items.
5. Identify material conflicts with the existing code.
6. Proceed without asking for confirmation unless implementation is unsafe or impossible.

## Laravel Standards

- Follow Laravel conventions before creating custom abstractions.
- Use Form Request classes for non-trivial validation.
- Use policies for authorization.
- Keep controllers thin.
- Use action or service classes only for multi-step business operations.
- Use database transactions for accounting postings, stock movements, and payment allocations.
- Use Eloquent relationships clearly and consistently.
- Prevent N+1 queries on report and listing screens.
- Use pagination for potentially large tables.
- Use named routes.
- Use route model binding where appropriate.
- Avoid business logic in Blade templates.
- Avoid financial calculations in JavaScript.

## Financial and Accounting Standards

- Never use floating-point database fields for money.
- Default monetary columns to `decimal(19, 4)`.
- Default quantity columns to `decimal(19, 4)`.
- Default rate columns to `decimal(9, 6)`.
- Centralize material accounting and tax calculations in tested PHP classes.
- Store tax rates and tax rules as configurable effective-dated settings.
- Never hard-code a percentage tax rate into transaction posting logic.
- Never hard-delete posted financial transactions.
- Use draft, posted, voided, and reversed states where appropriate.
- Require a reason for voiding or reversing posted transactions.
- Record audit information for material financial changes.
- Preserve gross sales separately from customer withholding deductions.
- Do not treat owner withdrawals as business expenses.
- Do not claim that the system directly files returns with BIR.
- Tax outputs are preparation worksheets subject to owner or accountant review.

## Database Standards

- Use bigint primary keys unless a work package specifies otherwise.
- Use foreign keys and appropriate indexes.
- Use unique constraints for controlled document numbers.
- Use `date` for accounting dates.
- Use timestamps for system activity.
- Add status indexes to large transactional tables when justified.
- Migrations must support `php artisan migrate:fresh`.
- Seeders must be deterministic where practical.
- Avoid polymorphic relationships unless they materially simplify the design.
- Do not create duplicate master records for customers, suppliers, products, or accounts.

## Blade and Frontend Standards

- Use Blade for all application screens.
- Use Alpine.js only for small interactions.
- Keep forms server-rendered and accessible.
- Use reusable Blade components only when repetition is proven.
- Prioritize clear data entry over animation.
- Support desktop and mobile layouts.
- Display validation errors beside their fields.
- Require confirmation for destructive or irreversible actions.
- Avoid large JavaScript dependencies.
- Keep tables readable and provide filters only when needed.

## Testing Standards

Each work package must include focused tests for:

- Successful operation
- Validation failure
- Authorization
- Important business rules
- Financial calculations, where applicable

During implementation, run only targeted tests.

Before closing a work package, run:

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
```

On Windows, use the applicable vendor executable format when necessary.

Do not pursue 100 percent test coverage. Prioritize high-risk financial and authorization behavior.

## Token-Efficiency Rules

- Do not repeatedly summarize the entire project.
- Do not read every file in the repository.
- Do not print unchanged code.
- Do not reproduce complete files unless newly created or materially changed.
- Do not generate long progress narratives.
- Report discoveries only when they affect implementation.
- Use file paths and short change summaries.
- Keep each work package small, preferably 5 to 15 changed files.
- Do not generate documentation that is not required by the work package.
- Do not rewrite stable documentation without a concrete need.
- Use targeted searches rather than repository-wide inspection.

## Completion Report Format

Report only:

1. Files changed
2. Commands and tests run
3. Acceptance criteria result
4. Remaining issues requiring owner action
