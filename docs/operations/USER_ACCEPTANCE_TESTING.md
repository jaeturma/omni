# User Acceptance Testing Record

Environment: ____________________  Release/version: ____________________  Test window: ____________________

Use representative but non-production data. For every scenario, record the tester, date, actual result, evidence reference, status (`pass`, `fail`, or `blocked`), and defect ID when applicable. Never mark a row passed without retained evidence.

| ID | Critical workflow | Expected result | Tester/date | Actual result and evidence | Status / defect |
| --- | --- | --- | --- | --- | --- |
| UAT-01 | Initial business, tax, fiscal, user, role, and sequence setup | Approved profiles and permissions save; unauthorized access is denied; starting numbers match the approved register | | | |
| UAT-02 | Customer and supplier creation | Valid records save, duplicates/invalid data fail, and sensitive identifiers are masked for restricted users | | | |
| UAT-03 | Product and service creation | Inventory products and non-inventory services retain correct units, categories, tax/cost flags, and validation | | | |
| UAT-04 | Quotation through collection | Conversion, delivery, invoice, withholding, allocation, balances, document numbers, and source links reconcile | | | |
| UAT-05 | Purchase request through supplier payment | Approval, canvass, order, receipt, invoice, withholding, allocation, and payable balances reconcile | | | |
| UAT-06 | Direct operating expense | Paid, unpaid, and reimbursable cases post only through the approved workflow and preserve evidence | | | |
| UAT-07 | Cash receipt and disbursement | Posted cash activity changes the selected account once and voiding creates controlled reversal effects | | | |
| UAT-08 | Fund transfer and petty cash | Source, destination, in-transit, voucher, replenishment, and balances reconcile | | | |
| UAT-09 | Inventory receipt and sale issue | Quantities and weighted-average cost post once to the correct product and warehouse | | | |
| UAT-10 | Physical count | Blind count, variance review, approval, and resulting adjustment remain traceable | | | |
| UAT-11 | Journal review and reversal | Only balanced/open-period journals post; posted lines are immutable; reversal/correction retains links and reason | | | |
| UAT-12 | Bank reconciliation | Imported lines, matches, adjustments, completion, and statement/account balances reconcile | | | |
| UAT-13 | Financial statements | Trial balance, AR/AP/cash/inventory controls, income statement, balance sheet, cash flow, and owner equity reconcile | | | |
| UAT-14 | 2551Q preparation | Configured rule, gross sales, exclusions, withholding credits, evidence, review, revision, and payable reconcile | | | |
| UAT-15 | 1701Q preparation when registered | Cumulative values, credits, exact decimals, whole-peso encoding values, evidence, review, and revision reconcile | | | |
| UAT-16 | Withholding certificates and books export | Certificate status/application and book/schedule CSV totals match approved sources | | | |
| UAT-17 | Attachments, privacy, audit, and monitoring | Unauthorized downloads fail; authorized files open; sensitive output is masked; material actions and failures are visible | | | |
| UAT-18 | Backup, restore, deployment, and rollback | Backup is verified/off-site, isolated restore passes, candidate deploys, smoke checks pass, and rollback rehearsal meets RTO/RPO | | | |

## Defect register

Severity definitions: `critical` prevents safe operation, corrupts/reveals protected data, or breaks financial integrity; `high` blocks a critical workflow without a safe workaround; `medium` has a controlled workaround; `low` is cosmetic or minor usability impact.

| Defect ID | Scenario | Severity | Description/evidence | Owner | Resolution or accepted deferral | Retest evidence/status |
| --- | --- | --- | --- | --- | --- | --- |
| | | | | | | |

Critical and high defects must be resolved and retested before sign-off. A deferred medium/low defect requires named owner approval, compensating control, and review date.

## Owner sign-off

I confirm that the recorded scenarios were performed against the stated release, evidence was reviewed, all critical/high defects were resolved, and any remaining risks are explicitly accepted.

Owner name/signature: ____________________  Date: ____________________

Bookkeeper/accountant name/signature: ____________________  Date: ____________________

Approved release/version: ____________________  Evidence-pack reference: ____________________

