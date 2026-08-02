# Owner and Bookkeeper Operating Procedures

These procedures are concise operating controls. Use authorized accounts, attach source evidence, never share credentials, and never edit or delete posted financial records outside the controlled void/reversal workflows. Screenshot placeholders must be replaced with release-matched images during UAT.

## Daily transaction entry

1. Verify the document date, counterparty, fiscal period, sequence number, quantities, gross amount, withholding, and attachments.
2. Save as draft, obtain required approval, post once, and confirm the source link and resulting balance.
3. Resolve failed postings before end of day; do not recreate a source transaction to bypass an error.

Screenshot: `[daily-entry-and-posting.png]`

## Daily cash review

Compare cash receipts/disbursements and account balances to physical cash, official receipts, deposit slips, and bank activity. Investigate unapplied payments, in-transit transfers, and differences before close of business.

Screenshot: `[daily-cash-position.png]`

## Weekly receivable and payable review

Review aging, overdue invoices, unapplied advances, expected/actual withholding, supplier obligations, and supporting documents. Record collection/payment actions without changing historical due dates or posted amounts.

Screenshot: `[weekly-aging-review.png]`

## Monthly bank reconciliation

Import the complete statement, review duplicates, match posted transactions, document approved adjustments, investigate every outstanding item, and complete only when the statement and book balance reconcile.

Screenshot: `[bank-reconciliation.png]`

## Monthly inventory review

Review negative/low stock alerts, stock ledger, warehouse transfers, unposted receipts/issues, weighted-average cost, and valuation. Perform controlled physical counts and approve documented variance adjustments.

Screenshot: `[inventory-review.png]`

## Month-end accounting review

Clear draft/failed postings, reconcile AR/AP/cash/inventory/tax controls, review reversals and bank reconciliation, post supported adjustments, generate statements, resolve critical close checks, then close and lock with notes and approval.

Screenshot: `[month-end-close.png]`

## Quarterly tax preparation

Confirm Certificate of Registration applicability and current official rules, generate the tax calendar and reconciliation, investigate differences, prepare the registered worksheet, review exact and encoding amounts, validate credits/evidence, approve the frozen revision, then separately record actual filing/payment evidence. Omni does not file with BIR.

Screenshot: `[quarterly-tax-review.png]`

## Document attachment handling

Upload only necessary supported types, verify the document opens and belongs to the correct record, restrict sensitive downloads, and never place private attachments under the public web root. Delete only when the source remains editable and a reason is recorded.

Screenshot: `[private-attachment.png]`

## Voiding, reversal, and correction

Stop and confirm the source, period, downstream effects, authority, and reason. Void through the document workflow or reverse the original journal; never edit posted lines. Repost a corrected replacement with explicit links and re-run reconciliations.

Screenshot: `[reversal-workflow.png]`

## User management

Use named accounts and least-privilege roles. Disable departed users promptly, review roles quarterly, test the last-administrator safeguard, reset compromised passwords, and investigate repeated login/authorization failures.

Screenshot: `[user-role-review.png]`

## Backup verification

Confirm daily/weekly/monthly schedule status, encryption, size/hash, off-server copy, and freshness. Perform an isolated restore at least quarterly and before go-live or major migration; record elapsed time and smoke/reconciliation results.

Screenshot: `[backup-status.png]`

## Incident reporting

Preserve evidence and timestamps, record affected users/data/workflows, correlation IDs, logs, release, and actions. For suspected data loss, exposure, or financial corruption, stop writes, notify the owner, preserve the database, invoke the recovery runbook, and do not resume without documented approval.

Screenshot: `[health-and-incident.png]`

## Production activation checklist

- Owner-approved immutable release and maintenance window
- Passing MySQL fresh migration, complete suite, Pint, PHPStan, asset build, routes, caches, and staging smoke tests
- Completed UAT evidence and signatures with no unresolved critical/high defect
- Approved cutover date, source pack, balanced trial balance, reconciled subledgers, cash/equity/tax confirmations, and sequences
- Verified encrypted off-site pre-activation backup and isolated restore/rollback rehearsal
- HTTPS, secure cookies, least-privilege permissions, scheduler, logs, alerts, disk capacity, version, and deployment timestamp verified
- Post-activation health, login, critical workflow, attachment, financial, tax, scheduler, and backup checks assigned
