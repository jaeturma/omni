# Purchasing Workflow Conventions

## Scope

These conventions define the shared Phase 4 baseline. They do not create purchase requests, canvasses, purchase orders, receiving records, supplier invoices, supplier payments, expenses, allocations, stock movements, inventory costs, journal entries, reports, or tax calculations.

## Workflows

Direct purchase:

1. Create a draft supplier invoice from the supplier's source document.
2. Validate supplier terms and all amounts server-side.
3. Post the invoice and consume its `purchase_invoice` number.
4. Record and post a supplier payment using a `supplier_payment` number.
5. Allocate posted payments to posted invoices inside a database transaction.

Purchase-order purchase:

1. Draft and submit a purchase request; submission consumes its `purchase_request` number.
2. If canvassing is required, record comparable supplier offers, evaluate them, and retain the award approval metadata. Canvasses use the purchase-request reference and do not consume a separate controlled number.
3. Create and approve a purchase order; issuance consumes its `purchase_order` number.
4. Record goods or services received; posting consumes a `receiving_report` number but does not calculate inventory cost.
5. Match and post the supplier invoice using a `purchase_invoice` number.
6. Post and allocate supplier payments as in the direct-purchase workflow.

Expense vouchers consume an `expense_voucher` number when posted. Saving drafts, previews, internal approvals, and rejected or cancelled documents do not consume numbers.

## Statuses and Transitions

- Purchase request: `draft` to `submitted` or `cancelled`; `submitted` to `approved`, `rejected`, or `cancelled`; `approved` to `converted` or `cancelled`.
- Canvass: `draft` to `open` or `cancelled`; `open` to `evaluated` or `cancelled`; `evaluated` to `awarded` or `cancelled`.
- Purchase order: `draft` to `approved` or `cancelled`; `approved` to `issued` or `cancelled`; `issued` to `partially_received`, `fully_received`, or `cancelled`; `partially_received` to `fully_received` or `cancelled`; `fully_received` to `closed`.
- Receiving: `draft` to `posted`; `posted` to `voided`.
- Supplier invoice: `draft` to `posted`; `posted` to `partially_paid`, `paid`, `overdue`, or `voided`; `partially_paid` and `overdue` may progress to another payment state or `voided`.
- Supplier payment: `draft` to `posted`; `posted` to `partially_allocated`, `fully_allocated`, or `voided`; allocated states may progress as defined centrally and may be voided.
- Expense: `draft` to `approved` or `voided`; `approved` to `posted` or `voided`; `posted` to `paid` or `voided`; `paid` to `voided`.
- Supplier-payment allocation: `active` to `reversed`.

Terminal states have no outgoing transitions. Posted supplier invoices and payments cannot return to draft or be edited. Voiding retains the document number and requires a reason, acting user, and timestamp. Allocations are reversed rather than deleted.

## References and Approval Metadata

Future purchasing records must retain applicable supplier source references and links to their originating request, canvass award, purchase order, receiving record, invoice, payment, or expense. Approval-controlled documents must preserve `submitted_at`, `submitted_by`, `approved_at`, `approved_by`, and any rejection or cancellation reason needed by their workflow. Posted documents additionally preserve posting and void metadata. These conventions do not authorize those transaction columns or tables in this work package.

Supplier master records already store payment terms as a non-negative number of days, so no duplicate payment-term table is introduced.

## Amount Conventions

- All calculations run in PHP using decimal strings; JavaScript is not authoritative.
- Money is rounded half-up to four decimal places after each line extension and discount calculation.
- Line gross amount is quantity multiplied by unit cost; discounts reduce gross purchase.
- Freight is added after discounts and remains separately identifiable.
- Withholding and cash paid reduce the balance due but never reduce gross purchase or become purchase discounts.
- Balance due is net purchase plus freight, less withholding and cash paid.
- Quantities and money accept at most four decimal places; percentage rates accept at most six decimal places.

## Transaction Safety

Future posting, allocation, status-change, voiding, and reversal operations must execute inside database transactions and lock affected records where concurrency could duplicate numbers or over-allocate balances. Receiving records must not calculate inventory cost during Phase 4. No general-ledger, inventory-costing, financial-reporting, or tax-return behavior is authorized by these conventions.
