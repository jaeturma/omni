# Codex Workflow

## One Work Package Per Session

Use one Codex conversation for one work package whenever practical.

Do not ask Codex to build the entire mini-ERP in one request.

## Standard Execution Prompt

```text
Execute `docs/work-packages/<WORK-PACKAGE-FILE>.md`.

Follow `AGENTS.md` strictly.

Work only within the stated scope. Inspect only the files required to verify and complete this work package. Do not implement future modules or modify unrelated files.

Run the required checks and make only the minimum necessary changes. Stop after the acceptance criteria are satisfied.

In the completion report, include only:

1. Files changed
2. Commands and tests run
3. Acceptance criteria result
4. Remaining issues requiring owner action
```

## Recommended Sequence

1. WP-00-01 Repository and Laravel Baseline
2. WP-00-02 Environment Configuration
3. WP-00-03 Quality Tool Verification
4. WP-00-04 Authentication Baseline
5. WP-00-05 Application Shell
6. WP-00-06 Database Conventions
7. WP-00-07 Audit and Document Status Conventions

Do not begin business modules before Phase 0 is complete.

## Token-Saving Rules

- Do not paste the whole project history into Codex.
- Refer Codex to the exact work-package file.
- Avoid prompts such as “inspect everything.”
- Ask for targeted tests during implementation.
- Run the full suite only before closing the work package.
- Keep work packages small.
- Commit after every completed work package.
- Start a fresh conversation for a new major module.
- Do not ask Codex to automatically continue.
- Review `git diff` before committing.

## Suggested Git Branches

```text
main
feature/wp-00-01-repository-baseline
feature/wp-00-02-environment-configuration
feature/wp-00-03-quality-tools
feature/wp-00-04-authentication-baseline
feature/wp-00-05-application-shell
```

## Completion Review

Before merging a work package:

```bash
git status
git diff
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
```
