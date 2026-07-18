<?php

namespace App\Support;

use App\Enums\CashTransactionType;

final class CashBankWorkflow
{
    public const DOCUMENT_SEQUENCES = ['cash_receipt' => 'cash_receipt', 'cash_disbursement' => 'cash_disbursement',
        'fund_transfer' => 'fund_transfer', 'petty_cash' => 'petty_cash_voucher', 'cash_adjustment' => 'cash_adjustment'];

    public const SOURCE_LINKED_TYPES = [CashTransactionType::CustomerReceipt, CashTransactionType::SupplierPayment, CashTransactionType::ExpensePayment];

    public const MANUAL_TYPES = [CashTransactionType::Deposit, CashTransactionType::Withdrawal, CashTransactionType::Adjustment, CashTransactionType::OpeningBalance];

    public const TRANSFER_TYPES = [CashTransactionType::TransferOut, CashTransactionType::TransferIn];

    public const PETTY_CASH_TYPES = [CashTransactionType::PettyCashRelease, CashTransactionType::PettyCashReplenishment];

    public const PERMISSIONS = [
        'financial-accounts.view', 'financial-accounts.create', 'financial-accounts.update', 'financial-accounts.close',
        'cash-receipts.view', 'cash-receipts.create', 'cash-receipts.update', 'cash-receipts.post', 'cash-receipts.void', 'cash-receipts.print',
        'cash-disbursements.view', 'cash-disbursements.create', 'cash-disbursements.update', 'cash-disbursements.post', 'cash-disbursements.void', 'cash-disbursements.print',
        'fund-transfers.view', 'fund-transfers.create', 'fund-transfers.post', 'fund-transfers.void',
        'petty-cash.view', 'petty-cash.create', 'petty-cash.post', 'petty-cash.void', 'petty-cash.replenish',
        'bank-statements.view', 'bank-statements.import', 'bank-statements.delete',
        'bank-reconciliations.view', 'bank-reconciliations.create', 'bank-reconciliations.complete', 'bank-reconciliations.reopen',
        'cash-reports.view', 'cash-reports.export',
    ];

    public const ENCODER_PERMISSIONS = ['financial-accounts.view', 'cash-receipts.view', 'cash-receipts.create', 'cash-receipts.update', 'cash-receipts.print',
        'cash-disbursements.view', 'cash-disbursements.create', 'cash-disbursements.update', 'cash-disbursements.print',
        'fund-transfers.view', 'fund-transfers.create', 'petty-cash.view', 'petty-cash.create', 'bank-statements.view', 'bank-statements.import',
        'bank-reconciliations.view', 'cash-reports.view'];

    public const VIEW_PERMISSIONS = ['financial-accounts.view', 'cash-receipts.view', 'cash-receipts.print', 'cash-disbursements.view',
        'cash-disbursements.print', 'fund-transfers.view', 'petty-cash.view', 'bank-statements.view', 'bank-reconciliations.view', 'cash-reports.view'];

    private function __construct() {}
}
