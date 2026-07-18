<?php

namespace App\Support;

final class PurchasingWorkflow
{
    public const DOCUMENT_SEQUENCES = [
        'purchase_request' => 'purchase_request',
        'purchase_order' => 'purchase_order',
        'receiving' => 'receiving_report',
        'supplier_invoice' => 'purchase_invoice',
        'supplier_payment' => 'supplier_payment',
        'expense' => 'expense_voucher',
    ];

    public const PERMISSIONS = [
        'purchase-requests.view', 'purchase-requests.create', 'purchase-requests.update', 'purchase-requests.submit', 'purchase-requests.approve', 'purchase-requests.reject', 'purchase-requests.cancel', 'purchase-canvass.manage',
        'canvasses.view', 'canvasses.create', 'canvasses.update', 'canvasses.evaluate', 'canvasses.award', 'canvasses.cancel',
        'purchase-orders.view', 'purchase-orders.create', 'purchase-orders.update', 'purchase-orders.approve', 'purchase-orders.issue', 'purchase-orders.send', 'purchase-orders.cancel', 'purchase-orders.print',
        'receivings.view', 'receivings.create', 'receivings.update', 'receivings.post', 'receivings.void', 'receivings.print',
        'receiving-records.view', 'receiving-records.create', 'receiving-records.inspect', 'receiving-records.accept', 'receiving-records.cancel', 'receiving-records.print',
        'supplier-invoices.view', 'supplier-invoices.create', 'supplier-invoices.update', 'supplier-invoices.post', 'supplier-invoices.void', 'supplier-invoices.print',
        'supplier-payments.view', 'supplier-payments.create', 'supplier-payments.update', 'supplier-payments.post', 'supplier-payments.allocate', 'supplier-payments.void', 'supplier-payments.print',
        'expenses.view', 'expenses.create', 'expenses.update', 'expenses.approve', 'expenses.post', 'expenses.void', 'expenses.print',
        'payables.view', 'payables.export', 'supplier-statements.view',
        'purchasing-attachments.view', 'purchasing-attachments.upload', 'purchasing-attachments.delete',
    ];

    public const ENCODER_PERMISSIONS = [
        'purchase-requests.view', 'purchase-requests.create', 'purchase-requests.update', 'purchase-requests.submit',
        'canvasses.view', 'canvasses.create', 'canvasses.update', 'purchase-canvass.manage',
        'purchase-orders.view', 'purchase-orders.create', 'purchase-orders.update', 'purchase-orders.print',
        'receivings.view', 'receivings.create', 'receivings.update', 'receivings.print', 'receiving-records.view', 'receiving-records.create', 'receiving-records.print',
        'supplier-invoices.view', 'supplier-invoices.create', 'supplier-invoices.update', 'supplier-invoices.print',
        'supplier-payments.view', 'supplier-payments.create', 'supplier-payments.update', 'supplier-payments.print',
        'expenses.view', 'expenses.create', 'expenses.update', 'expenses.print',
        'payables.view', 'supplier-statements.view', 'purchasing-attachments.view', 'purchasing-attachments.upload', 'purchasing-attachments.delete',
    ];

    public const VIEW_PERMISSIONS = [
        'purchase-requests.view', 'canvasses.view', 'purchase-orders.view', 'purchase-orders.print',
        'receivings.view', 'receivings.print', 'receiving-records.view', 'receiving-records.print', 'supplier-invoices.view', 'supplier-invoices.print',
        'supplier-payments.view', 'supplier-payments.print', 'expenses.view', 'expenses.print',
        'payables.view', 'supplier-statements.view', 'purchasing-attachments.view',
    ];

    private function __construct() {}
}
