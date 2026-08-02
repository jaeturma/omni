# Backup, Restore, and Disaster Recovery

## Service objectives

- Recovery point objective (RPO): 24 hours. A verified daily backup must exist; take an additional backup before every deployment and major migration.
- Recovery time objective (RTO): 4 hours from disaster declaration to an owner-approved smoke-tested service.
- A backup is successful only after its encrypted file hash and archive contents are verified. A copied but unverified file is not a successful backup.

## Backup classes and retention

| Class | Timing | Minimum retention |
| --- | --- | --- |
| Daily | 01:00 Asia/Manila | 14 days |
| Weekly full | Monday 02:00 | 8 weeks |
| Monthly retained | First day 03:00 | 13 months |
| Pre-deployment | Immediately before deployment | 30 days |
| Pre-major-migration | Immediately before migration | 30 days |

Every backup contains a transaction-consistent MySQL dump, the private filesystem (including sales, purchasing, and tax attachments), `.env.example`, application config source, and a manifest. It never includes `.env`. Archives are encrypted with sodium authenticated streaming encryption before local or off-server storage.

## Owner-controlled prerequisites

1. Generate `BACKUP_ENCRYPTION_KEY` on a trusted administrator workstation using the command documented in `.env.example`. Store it in the hosting secret manager and in the sealed recovery credential record. Never commit or place it inside a backup.
2. Configure `BACKUP_OFFSITE_DISK` as an S3, SFTP, or other Laravel disk whose credentials are injected by the host. The destination must use a different provider or failure domain from the application server.
3. Install compatible `mysqldump` and `mysql` clients and set their binary paths when they are not on `PATH`.
4. Restrict backup storage, scheduler, database, and secret-manager access to the owner and designated recovery administrator.

## Routine operations

- Ensure Laravel's scheduler runs every minute. Review `/backup-status` daily; it intentionally shows no paths or credentials.
- Before deployment: `php artisan backup:run --class=pre_deployment`.
- Before a major migration: `php artisan backup:run --class=pre_migration`.
- Independently reverify a run: `php artisan backup:verify <run-id>`.
- Investigate any `failed` or `corrupt` status immediately. Do not delete the prior verified generation until a replacement is verified and copied off-server.

## Quarterly restore exercise

1. Provision an empty, isolated MySQL database and a non-public exercise host using the same application release.
2. Record the exercise start time and selected verified backup ID.
3. Run `php artisan backup:restore-test <run-id> <unique-exercise-id> --database=<isolated-db>` from the exercise host. The command refuses the production database name.
4. Confirm the command authenticates and extracts the archive, imports the database, checks the migrations table, and restores private attachments.
5. Configure the exercise application to use only the restored database and restored private-file directory. Keep outbound mail and external integrations disabled.
6. Smoke-test login, dashboard, one posted sales invoice, one supplier invoice, inventory balances, general ledger, tax dashboard, and one private attachment download.
7. Reconcile key record counts and the latest posted document date against the backup manifest/time. Record elapsed time against the four-hour RTO.
8. Destroy the isolated database and extracted files securely after sign-off. Keep the audit event and exercise report, not restored business data.

## Disaster-recovery checklist

1. Owner declares the incident, starts the RTO clock, and prevents writes to the affected system.
2. Preserve logs and evidence; do not overwrite the failed host.
3. Select the newest off-server backup with `verified` status before the incident. Reverify its hash.
4. Provision a clean host, database, private storage, application release, and secrets from the approved credential store.
5. Restore using the quarterly procedure. Never restore over the failed production database.
6. Perform database, attachment, financial, inventory, tax, and authentication smoke tests.
7. Confirm the recovery point and document any transactions inside the RPO window that require controlled re-entry.
8. Owner approves service return; update DNS or routing, monitor closely, and retain incident evidence.
9. Record actual RPO/RTO, root cause, recovery actions, and follow-up controls.

## Failure handling

- Hash mismatch, failed authentication, missing manifest/database entry, truncated ciphertext, import error, or smoke-test failure marks the generation unusable.
- Keep at least one older verified off-server generation while investigating corruption.
- Loss of the encryption key makes encrypted backups unrecoverable. Key custody and recovery testing are owner responsibilities.
