<?php

namespace App\Http\Requests;

use App\Enums\SalesInvoiceStatus;
use App\Models\Delivery;
use App\Models\DeliveryLine;
use App\Models\FiscalPeriod;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSalesInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $invoice = $this->route('sales_invoice');

        return $invoice instanceof SalesInvoice
            ? (bool) $this->user()?->can('update', $invoice)
            : (bool) $this->user()?->can('create', SalesInvoice::class);
    }

    public function rules(): array
    {
        return [
            'source_type' => ['required', Rule::in(['direct', 'order', 'delivery'])],
            'sales_order_id' => [Rule::requiredIf($this->input('source_type') === 'order'), 'nullable', 'exists:sales_orders,id'],
            'delivery_id' => [Rule::requiredIf($this->input('source_type') === 'delivery'), 'nullable', 'exists:deliveries,id'],
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('status', 'active')],
            'fiscal_period_id' => ['required', 'exists:fiscal_periods,id'], 'invoice_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:invoice_date'], 'customer_po_number' => ['nullable', 'string', 'max:255'],
            'expected_withholding_amount' => ['nullable', 'decimal:0,4', 'gte:0'], 'notes' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.sales_order_line_id' => [Rule::requiredIf($this->input('source_type') === 'order'), 'nullable', 'distinct', 'exists:sales_order_lines,id'],
            'lines.*.delivery_line_id' => [Rule::requiredIf($this->input('source_type') === 'delivery'), 'nullable', 'distinct', 'exists:delivery_lines,id'],
            'lines.*.product_service_id' => ['nullable', 'exists:product_services,id'], 'lines.*.sku' => ['nullable', 'string', 'max:50'],
            'lines.*.description' => [Rule::requiredIf($this->input('source_type') === 'direct'), 'nullable', 'string', 'max:255'],
            'lines.*.uom_code' => [Rule::requiredIf($this->input('source_type') === 'direct'), 'nullable', 'string', 'max:20'],
            'lines.*.uom_name' => [Rule::requiredIf($this->input('source_type') === 'direct'), 'nullable', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'decimal:0,4', 'gt:0'],
            'lines.*.unit_price' => [Rule::requiredIf($this->input('source_type') === 'direct'), 'nullable', 'decimal:0,4', 'gte:0'],
            'lines.*.discount_rate' => ['nullable', 'decimal:0,6', 'between:0,100'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $invoice = $this->route('sales_invoice');
            if ($invoice instanceof SalesInvoice && $invoice->status !== SalesInvoiceStatus::Draft) {
                $validator->errors()->add('status', 'Posted invoices cannot be edited.');
            }
            $period = FiscalPeriod::find($this->input('fiscal_period_id'));
            if (! $period || $period->status !== 'open' || ! $period->starts_on->lte($this->date('invoice_date')) || ! $period->ends_on->gte($this->date('invoice_date'))) {
                $validator->errors()->add('invoice_date', 'The invoice date must belong to the selected open fiscal period.');
            }
            if ($this->input('source_type') === 'order' && ! SalesOrder::whereKey($this->input('sales_order_id'))->where('customer_id', $this->input('customer_id'))->exists()) {
                $validator->errors()->add('customer_id', 'The customer must match the selected order.');
            }
            if ($this->input('source_type') === 'delivery' && ! Delivery::whereKey($this->input('delivery_id'))->where('customer_id', $this->input('customer_id'))->exists()) {
                $validator->errors()->add('customer_id', 'The customer must match the selected delivery.');
            }
            foreach ($this->input('lines', []) as $index => $line) {
                if ($this->input('source_type') === 'order' && ! SalesOrderLine::whereKey($line['sales_order_line_id'] ?? 0)->where('sales_order_id', $this->input('sales_order_id'))->exists()) {
                    $validator->errors()->add("lines.{$index}.sales_order_line_id", 'The line must belong to the selected order.');
                }
                if ($this->input('source_type') === 'delivery' && ! DeliveryLine::whereKey($line['delivery_line_id'] ?? 0)->where('delivery_id', $this->input('delivery_id'))->exists()) {
                    $validator->errors()->add("lines.{$index}.delivery_line_id", 'The line must belong to the selected delivery.');
                }
            }
        }];
    }
}
