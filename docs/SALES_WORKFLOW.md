# Sales Workflow Conventions

## Scope

These conventions define the shared Phase 3 baseline. They do not create quotations, orders, deliveries, invoices, payments, allocations, stock movements, journal entries, reports, or tax calculations.

## Workflows

Direct sale:

1. Create a draft sales invoice.
2. Validate server-side amounts and the accounting date.
3. Post the invoice and consume its `sales_invoice` number.
4. Record and post customer payments using a `collection_receipt` number.
5. Allocate posted payments to posted invoices inside a database transaction.

Order-based sale:

1. Draft and issue a quotation using a `quotation` number.
2. Accept the quotation and create a draft sales order; confirmation consumes its `sales_order` number.
3. Create delivery records from the confirmed order; release consumes a `delivery_receipt` number.
4. Create and post the sales invoice using a `sales_invoice` number.
5. Post and allocate customer payments as in the direct-sale workflow.

Document numbers are consumed only at the issuance, confirmation, release, or posting point stated above. Previewing and saving drafts do not consume numbers.

## Statuses and Transitions

- Quotation: `draft` to `sent` or `cancelled`; `sent` to `accepted`, `rejected`, `expired`, or `cancelled`.
- Sales order: `draft` to `confirmed` or `cancelled`; `confirmed` to `partially_delivered`, `completed`, or `cancelled`; `partially_delivered` to `completed` or `cancelled`.
- Delivery: `draft` to `released` or `cancelled`; `released` to `delivered` or `cancelled`.
- Sales invoice: `draft` to `posted`; `posted` to `partially_paid`, `paid`, or `voided`; `partially_paid` to `paid` or `voided`.
- Customer payment: `draft` to `posted`; `posted` to `partially_allocated`, `fully_allocated`, or `voided`; `partially_allocated` to `fully_allocated` or `voided`.
- Payment allocation: `active` to `reversed`.

Terminal states have no outgoing transitions. Posted invoices and posted payments cannot return to draft or be edited. Voiding a posted document must retain the document number and record a reason, acting user, and timestamp. Allocations are reversed rather than deleted.

## Amount Conventions

- All calculations run in PHP using decimal strings; JavaScript may assist display but is not authoritative.
- Money is rounded half-up to four decimal places after each line extension and discount calculation.
- Line gross amount is quantity multiplied by unit price.
- Line discount is line gross amount multiplied by the configurable line discount rate.
- Net sales are gross sales less discounts.
- Withholding and cash received reduce the balance due but never reduce gross sales or become sales discounts.
- Balance due is net sales less withholding deductions and cash received.
- Quantities and money accept at most four decimal places; percentage rates accept at most six decimal places.

Customer and supplier master records already store payment terms as a non-negative number of days, so no duplicate payment-term table is introduced.

## Transaction Safety

Future posting, allocation, voiding, and reversal operations must execute inside database transactions. They must lock affected records where concurrent changes could duplicate numbers or over-allocate balances. No general-ledger, inventory, reporting, or tax behavior is authorized by these conventions.
