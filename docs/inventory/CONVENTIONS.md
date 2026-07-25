# Inventory Conventions

## Movement lifecycle

Inventory movements use `draft`, `posted`, and `reversed` statuses. Posting makes a movement append-only. A correction never edits or deletes a posted movement; it creates an attributed counter-movement linked to the original.

The supported movement types are opening balance, purchase receipt, sales issue, customer return, supplier return, adjustment in/out, transfer in/out, and physical-count gain/loss.

Receiving records are the source for purchase-receipt movements. Delivery records are the source for sales-issue movements. Each source line may produce at most one active posted movement; a reversal is a separate linked movement.

## Eligibility and stock policy

Only catalog entries whose type is `product` and whose inventory flag is enabled may create movements. Services and non-inventory products never affect stock.

Negative stock is blocked by default. Outbound posting must lock the affected product-and-warehouse balance and confirm that available quantity is at least the requested quantity before it appends a movement.

Quantities use `decimal(19, 4)`.

## Weighted-average costing

Weighted-average cost is maintained independently for each product and warehouse. Costs and inventory values use `decimal(19, 4)` and decimal-safe PHP arithmetic.

For an inbound movement:

`new average = ((quantity on hand × current average) + (received quantity × received unit cost)) ÷ new quantity on hand`

Outbound movements use the locked average cost immediately before posting and do not recalculate the average. Returns and reversals retain the costing details of their source movement when available. Zero-quantity balances retain the last calculated average for audit continuity; a later inbound movement establishes the next weighted average from its posted cost.

General-ledger and journal posting are outside Phase 6.
