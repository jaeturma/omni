<?php

namespace App\Support;

final class SalesWorkflow
{
    public const DOCUMENT_SEQUENCES = [
        'quotation' => 'quotation',
        'sales_order' => 'sales_order',
        'delivery' => 'delivery_receipt',
        'sales_invoice' => 'sales_invoice',
        'customer_payment' => 'collection_receipt',
    ];

    public const PERMISSIONS = [
        'quotations.view', 'quotations.create', 'quotations.update', 'quotations.approve', 'quotations.cancel', 'quotations.print',
        'sales-orders.view', 'sales-orders.create', 'sales-orders.update', 'sales-orders.confirm', 'sales-orders.cancel',
        'deliveries.view', 'deliveries.create', 'deliveries.release', 'deliveries.accept', 'deliveries.cancel', 'deliveries.print',
        'sales-invoices.view', 'sales-invoices.create', 'sales-invoices.update', 'sales-invoices.post', 'sales-invoices.void', 'sales-invoices.print',
        'customer-payments.view', 'customer-payments.create', 'customer-payments.update', 'customer-payments.post', 'customer-payments.allocate', 'customer-payments.void',
        'receivables.view', 'receivables.export', 'customer-statements.view', 'sales-withholding.manage',
    ];

    public const ENCODER_PERMISSIONS = [
        'quotations.view', 'quotations.create', 'quotations.update', 'quotations.print',
        'sales-orders.view', 'sales-orders.create', 'sales-orders.update', 'sales-orders.confirm',
        'deliveries.view', 'deliveries.create', 'deliveries.release', 'deliveries.print',
        'sales-invoices.view', 'sales-invoices.create', 'sales-invoices.update', 'sales-invoices.print',
        'customer-payments.view', 'customer-payments.create', 'customer-payments.update',
        'receivables.view', 'customer-statements.view',
    ];

    public const VIEW_PERMISSIONS = [
        'quotations.view', 'quotations.print', 'sales-orders.view', 'deliveries.view', 'deliveries.print', 'sales-invoices.view', 'sales-invoices.print',
        'customer-payments.view', 'receivables.view', 'customer-statements.view',
    ];

    private function __construct() {}
}
