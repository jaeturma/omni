# WP-00-03 — Quality Tool Verification

## Objective

Verify the minimum code-quality toolchain required for a small Laravel 13 project without introducing excessive tooling.

## Read First

- AGENTS.md
- docs/PROJECT_CONTEXT.md
- docs/DEVELOPMENT_RULES.md

## Dependencies

- WP-00-01 completed
- WP-00-02 completed

## Scope

- Verify Pest.
- Verify Laravel Pint.
- Install Larastan only if it is not already installed.
- Create or validate a minimal `phpstan.neon`.
- Use PHPStan level 5.
- Limit static analysis to application code.
- Confirm all baseline checks pass.

## Minimal PHPStan Configuration

```neon
includes:
    - vendor/larastan/larastan/extension.neon

parameters:
    paths:
        - app

    level: 5

    tmpDir: storage/framework/cache/phpstan
```

Do not increase the level during this work package.

## Out of Scope

- Mutation testing
- Browser testing
- Continuous integration
- Pre-commit hooks
- Coverage enforcement
- PHPStan level 8 or higher
- Full-repository refactoring
- Business modules

## Required Checks

```bash
composer show pestphp/pest
composer show laravel/pint
composer show larastan/larastan
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
```

## Acceptance Criteria

1. Pest is available.
2. Pint is available.
3. Larastan is available.
4. `phpstan.neon` uses level 5.
5. Static analysis is limited to application code.
6. Baseline tests pass.
7. Pint passes.
8. PHPStan passes, or remaining framework-generated issues are clearly documented.
9. No unnecessary tool is installed.
10. No business module is implemented.
