# WP-10-03 — Backup, Restore, and Disaster Recovery

## Objective

Create and test a reliable backup, restore, and disaster-recovery process for the database and private files.

## Read First

- AGENTS.md
- docs/phases/PHASE-10-PRODUCTION-READINESS-AND-HARDENING.md

## Scope

Design and implement operational procedures for:

- MySQL database backup
- Private attachment backup
- Application configuration backup
- Encryption of backup files
- Backup retention
- Off-server backup copy
- Backup verification
- Restore testing
- Recovery time objective
- Recovery point objective
- Disaster-recovery checklist

## Required Backup Classes

- Daily database backup
- Daily or incremental private-file backup
- Weekly full backup
- Monthly retained backup
- Pre-deployment backup
- Pre-major-migration backup

## Functional Requirements

- Do not include `.env` secrets in unsecured archives.
- Encrypt off-server backups.
- Record backup date, size, hash, location, and status.
- Verify that backups are readable.
- Test restore to a separate environment.
- Document owner actions and credentials not stored in the app.
- Provide a backup status screen only if it does not expose sensitive paths or credentials.
- Record backup and restore audit events.
- Do not claim successful backup without verification.

## Tests and Exercises

- Database backup creation
- File backup creation
- Hash verification
- Corrupt-backup detection
- Restore into isolated test database
- Attachment restore
- Post-restore application smoke test
- Permission restrictions

## Acceptance Criteria

1. Database and private files are backed up.
2. Backups are encrypted where required.
3. Backup verification works.
4. Restore is successfully tested.
5. RPO and RTO are documented.
6. Disaster-recovery steps are documented.
7. Tests or restore exercises pass.

## General Requirements

- Follow `AGENTS.md`.
- Use Laravel 13, Blade, Form Requests, policies, services/actions, and focused Pest tests.
- Keep the application as a modular monolith.
- Prefer framework-native features before adding packages.
- Document every new dependency and why it is required.
- Do not weaken financial immutability, auditability, or period controls.
- Do not expose secrets, TINs, bank details, or private attachments unnecessarily.
- Do not introduce speculative AI, mobile, payroll, CRM, or marketplace modules.
- Do not modify unrelated modules.

## Completion Report

Report only:

1. Files changed
2. Commands and tests run
3. Acceptance criteria result
4. Remaining issues requiring owner action
