# Development Rules

## General Rules

1. Follow Laravel conventions before creating custom abstractions.
2. Use Blade for all screens.
3. Use Alpine.js only for small interactive behavior.
4. Do not introduce React, Vue, Inertia, Livewire, or Filament.
5. Do not install a package without documenting why it is needed.
6. Do not refactor unrelated files.
7. Do not create speculative features.
8. Do not rewrite working code without a specific reason.
9. Work only on one approved work package at a time.
10. Keep all completion reports concise.

## Backend Rules

1. Use Form Request classes for non-trivial validation.
2. Use policies for authorization.
3. Keep controllers thin.
4. Use action or service classes only for multi-step business transactions.
5. Wrap accounting postings, inventory movements, and payment allocations in database transactions.
6. Never hard-delete posted financial transactions.
7. Use reversal, voiding, or adjustment workflows with recorded reasons.
8. Centralize consequential accounting and tax calculations.
9. Avoid business logic in Blade.
10. Avoid unnecessary repository or interface layers.

## Database Rules

1. Use bigint primary keys unless otherwise approved.
2. Store money as `decimal(19, 4)`.
3. Store quantities as `decimal(19, 4)`.
4. Store tax and percentage rates as `decimal(9, 6)`.
5. Use `date` fields for accounting dates.
6. Use timestamps for system activity.
7. Use foreign keys and appropriate indexes.
8. Use unique constraints for controlled document numbers.
9. Migrations must work with `php artisan migrate:fresh`.
10. Avoid duplicate or overlapping master-data tables.
11. Do not store derived totals when they can be safely calculated, unless performance or audit requirements justify storing them.
12. Store posted transaction totals when immutability and auditability require them.

## Financial Rules

1. Never use floating-point values for monetary calculations.
2. Preserve gross sale values separately from withholding deductions.
3. Do not reduce sales because a customer withheld tax.
4. Do not treat owner drawings as expenses.
5. Do not post incomplete or unbalanced journal entries.
6. Do not allow changes to closed accounting periods without an authorized reopening process.
7. Tax rates must be effective-dated and configurable.
8. Tax worksheets must show their source transactions.
9. Posted documents must retain their original document number.
10. Financial corrections must be auditable.

## Frontend Rules

1. Keep forms server-rendered.
2. Use reusable Blade components only when repetition is proven.
3. Prioritize speed and clarity over visual effects.
4. Support desktop and mobile displays.
5. Display validation errors beside the affected fields.
6. Require confirmation before voiding or reversing.
7. Avoid large JavaScript libraries.
8. Use filters and search only where users need them.
9. Use pagination for large tables.
10. Keep financial amounts consistently formatted.

## Testing Rules

1. Every completed work package must include focused feature tests.
2. Test financial calculations explicitly.
3. Test authorization and validation.
4. Test posting, voiding, and reversal rules when applicable.
5. Do not pursue 100 percent coverage.
6. Run targeted tests during development.
7. Run the full suite only before closing a work package.

## Completion Requirements

A work package is complete only when:

- All acceptance criteria pass.
- Relevant tests pass.
- The full test suite passes before final completion.
- Pint passes.
- PHPStan passes for modified application code.
- Migrations run from a fresh database.
- Documentation affected by the implementation is updated.
- No unrelated module was changed.
