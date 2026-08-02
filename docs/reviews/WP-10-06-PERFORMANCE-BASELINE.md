# WP-10-06 Performance Baseline

Measured on August 2, 2026 with the SQLite test database. These are regression baselines, not production-capacity claims; MySQL 8 measurements remain an owner deployment check.

| Workflow | Baseline evidence | WP-10-06 result |
| --- | --- | --- |
| Financial dashboard | Existing representative-data test stays below 100 queries | Bounded independently of transaction-row growth; no cache or new infrastructure added |
| Customer, supplier, product, sales-invoice, and supplier-invoice lists | Controller review and pagination tests | 20–25 rows per page; displayed relationships eager-loaded where needed |
| Sales-invoice list | 60 representative invoices | 8 request queries including authentication and authorization; 25 rows returned on page 1 |
| Sales and supplier invoice posting | Focused transaction tests | Transactional posting behavior retained; no additional posting query path introduced |
| Receivable and payable aging | Before: CSV built the complete mapped collection in memory | After: lazy eager-loaded chunks; 60 receivables plus 60 payables in 20-row chunks complete in no more than 14 queries |
| Stock ledger | Controller/report review and focused export test | 50-row interactive pages; CSV uses eager-loaded 200-row chunks |
| General journal and ledger | Before: export called `cursor()->collect()` and cursor eager loading was ineffective | After: CSV uses eager-loaded 500-row lazy chunks; running balances are attached while iterating |
| Trial balance and financial statements | Report review and reconciliation tests | Database aggregates remain bounded; account/result rows are paginated where applicable |
| Tax reconciliation | Period-scoped service and controller review | Explicit tax-period bounds and eager-loaded reconciliation relations retained |
| Attachment and audit-log listings | Controller review and audit tests | Attachment relations remain source-record scoped; audit list is 50 rows/page and export uses 500-row eager-loaded chunks |

## Index evidence

The aging queries filter by invoice date and order by due date plus ID, journal reports filter by status/date and order by date plus ID, and audit logs order by occurrence time plus ID. WP-10-06 adds matching compound indexes and verifies their installed names through the schema API:

- `sales_invoice_aging_order_index` (`invoice_date`, `due_date`, `id`)
- `supplier_invoice_aging_order_index` (`invoice_date`, `due_date`, `id`)
- `journal_status_date_order_index` (`status`, `journal_date`, `id`)
- `audit_occurred_order_index` (`occurred_at`, `id`)

No Redis, cache layer, denormalized financial records, or third-party dependency was introduced.
