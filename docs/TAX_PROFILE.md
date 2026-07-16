# Tax Profile

## Taxpayer

- Legal form: Sole proprietorship
- Registration type: Non-VAT
- Country: Philippines
- Business started: May 2026
- First reporting quarter: Q2 2026
- Initial percentage tax return: BIR Form 2551Q
- Initial quarter coverage: May 2026 through June 30, 2026, subject to the taxpayer's actual registration date and BIR records

## Important Configuration Rule

The application must not permanently assume:

- a fixed percentage tax rate
- a fixed income tax option
- a fixed filing deadline
- a fixed tax base
- a fixed list of required returns

Tax obligations must be stored as configurable and effective-dated settings.

## Required Tax Settings

The future tax profile module should support:

- taxpayer type
- VAT or non-VAT status
- income tax method or election
- percentage tax registration
- percentage tax rate
- effective date
- filing frequency
- RDO
- TIN
- branch code
- registered business name
- trade name
- registered address
- registration start date
- applicable BIR forms
- registered books of accounts

## 2551Q Preparation Principle

The system should prepare a reconciliation that shows:

- gross taxable sales or receipts
- excluded or exempt transactions, when applicable
- taxable gross amount
- applicable rate
- gross percentage tax
- allowable credits
- government percentage tax withheld, when supported
- net tax payable
- source transactions
- filing status
- payment reference
- attachment references

Purchases and ordinary operating expenses must not automatically reduce the percentage tax base.

## Government Transactions

Government customer records should support:

- agency name
- TIN
- purchase order number
- delivery and inspection references
- gross invoice amount
- percentage tax withheld
- expanded withholding tax
- other deductions
- net amount received
- withholding certificate type
- certificate number
- certificate date
- attachment

Gross sales must remain separate from withholding deductions.

## System Limitation

The system is a bookkeeping and tax-preparation tool. It must not claim that:

- it directly files returns with BIR
- its generated books are automatically BIR-registered books
- its tax calculations replace professional tax advice

All tax outputs must remain reviewable by the owner, bookkeeper, accountant, or tax professional.
