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
        'quotations.view', 'quotations.create', 'quotations.update', 'quotations.issue', 'quotations.cancel',
        'sales-orders.view', 'sales-orders.create', 'sales-orders.update', 'sales-orders.confirm', 'sales-orders.cancel',
        'deliveries.view', 'deliveries.create', 'deliveries.update', 'deliveries.release', 'deliveries.cancel',
        'sales-invoices.view', 'sales-invoices.create', 'sales-invoices.update', 'sales-invoices.post', 'sales-invoices.void',
        'customer-payments.view', 'customer-payments.create', 'customer-payments.update', 'customer-payments.post', 'customer-payments.void',
        'payment-allocations.manage', 'receivables.view', 'sales-withholding.manage',
    ];

    public const ENCODER_PERMISSIONS = [
        'quotations.view', 'quotations.create', 'quotations.update', 'quotations.issue',
        'sales-orders.view', 'sales-orders.create', 'sales-orders.update', 'sales-orders.confirm',
        'deliveries.view', 'deliveries.create', 'deliveries.update', 'deliveries.release',
        'sales-invoices.view', 'sales-invoices.create', 'sales-invoices.update',
        'customer-payments.view', 'customer-payments.create', 'customer-payments.update',
        'receivables.view',
    ];

    public const VIEW_PERMISSIONS = [
        'quotations.view', 'sales-orders.view', 'deliveries.view', 'sales-invoices.view',
        'customer-payments.view', 'receivables.view',
    ];

    private function __construct() {}
}
