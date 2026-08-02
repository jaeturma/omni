# Production Cutover and Opening Balances

Production activation requires explicit owner approval. Do not create placeholder balances or sign-offs.

1. Set the cutover date and freeze legacy/manual books. Record the immutable freeze reference and retain source documents.
2. Configure profiles, users, master data, fiscal periods, chart of accounts, and approved document-sequence starting numbers.
3. Encode AR and AP through customer- and supplier-linked opening journal lines. Encode cash with financial-account links, inventory through controlled inventory opening batches, and owner equity, loans, liabilities, and tax controls through balanced opening journal lines.
4. Post all opening batches on the cutover date. Never alter posted records; reverse and replace errors through the existing controlled workflows.
5. Reconcile the trial balance, AR, AP, inventory by item/warehouse, cash to count/bank statements, owner equity, tax controls, and outstanding withholding certificates.
6. Run and verify `php artisan backup:run --class=pre_deployment`, confirm its off-site copy, and complete an isolated restore exercise.
7. Record the backup, rollback rehearsal, source documents, confirmations, and cutover date on the Production Cutover screen. A different authorized reviewer approves the report after investigating every difference.
8. Activate only the approved record. Retain the report snapshot, source pack, approval, backup, and deployment record together.

Rollback does not delete or edit opening entries. Enter maintenance mode, preserve the failed database, restore the verified backup to a clean database, reconcile record counts and balances, and reactivate only with owner approval.
