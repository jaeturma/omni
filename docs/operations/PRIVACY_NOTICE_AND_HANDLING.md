# Privacy Notice and Internal Handling Procedure

## Purpose and scope

Omni Mini-ERP processes proprietor, customer, supplier, user, financial, tax, attachment, audit, backup, and operational-log information solely for legitimate business administration, fulfillment, accounting, tax preparation, security, and legal recordkeeping. The application is not a public directory and does not authorize unrelated reuse or disclosure.

## Classification

- **Public:** owner-approved information intended for publication.
- **Internal:** routine operational data available to authenticated staff according to role.
- **Confidential:** names, contacts, addresses, user information, and commercial details. Use only when required for assigned work.
- **Restricted:** TINs, bank references, transactions, tax records, private evidence, audit trails, backups, credentials, and logs. These require explicit permissions, private storage, and encryption where configured.

## Individual notice and requests

The business should tell customers, suppliers, users, and other individuals what information is collected, the legitimate purpose, expected recipients, retention basis, and how to request access or correction. The owner verifies identity before using the authorized data-subject lookup or export. Exported files are restricted working copies: transmit them securely, record the recipient and purpose, and delete the working copy after the request is resolved.

## Internal handling

1. Collect only information required for a documented business, contractual, tax, accounting, or security purpose.
2. Use masked list views for routine work. Reveal or export restricted values only with separate permission and a current need.
3. Never place passwords, tokens, authorization headers, cookies, full TINs, bank accounts, personal contact data, or private attachments in application logs or support messages.
4. Store attachments on the private disk. Keep backups encrypted and off-server according to the disaster-recovery procedure.
5. Do not share accounts. Remove access promptly when responsibilities change or employment ends.
6. Report suspected loss, unauthorized disclosure, or access to the owner immediately and preserve audit evidence.

## Retention, archive, and disposal

Retention-policy records define the classification, trigger, period, basis, and disposition review. Default financial, tax, and supporting-evidence periods are conservative operating baselines and must be reviewed by the owner or adviser against current Philippine requirements. A reached retention date is not permission to delete.

- Posted financial transactions, tax filings, audit records, and linked evidence remain immutable or protected from casual deletion.
- Inactive customer or supplier masters may be archived non-destructively; transaction snapshots and source relationships remain intact.
- Anonymization or disposal requires `records.dispose`, documented legal review, confirmation that no hold or linked obligation exists, and a verified backup under an approved policy.
- This package performs no automatic destructive purge. Disposal must be separately approved, auditable, and executed under a future owner-approved procedure.

## Review

The owner reviews classifications, permissions, retention periods, privacy notices, data-subject requests, archives, and proposed disposals at least annually and whenever law, business purpose, or system processing changes.
