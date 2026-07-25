<?php

namespace Database\Seeders;

use App\Enums\FinancialAccountType;
use App\Models\Bank;
use App\Models\BusinessProfile;
use App\Models\CashDisbursement;
use App\Models\CashReceipt;
use App\Models\Category;
use App\Models\Customer;
use App\Models\FinancialAccount;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Models\PaymentMethod;
use App\Models\ProductService;
use App\Models\Quotation;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $administrator = User::query()->updateOrCreate(
                ['email' => 'demo@omni.app'],
                ['name' => 'Demo Administrator', 'password' => Hash::make('password'), 'email_verified_at' => now()],
            );
            $administrator->syncRoles(['Administrator']);

            $profile = BusinessProfile::query()->active()->first() ?? BusinessProfile::query()->create([
                'registered_business_name' => 'Omni ICT and Office Supplies',
                'trade_name' => 'Omni Mini-ERP Demo',
                'proprietor_name' => 'Juan Dela Cruz',
                'tin' => '123-456-789-000',
                'branch_code' => '00000',
                'rdo_code' => '050',
                'registration_date' => '2026-01-02',
                'business_start_date' => '2026-01-02',
                'registered_address' => 'National Highway, San Fernando, Pampanga',
                'email' => 'accounts@omni.example',
                'phone' => '0917-555-0100',
                'default_currency' => 'PHP',
                'timezone' => 'Asia/Manila',
                'fiscal_year_start_month' => 1,
                'active' => true,
                'created_by' => $administrator->id,
                'updated_by' => $administrator->id,
            ]);

            $fiscalYear = FiscalYear::query()->updateOrCreate(
                ['business_profile_id' => $profile->id, 'name' => 'FY 2026'],
                ['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'is_current' => true, 'created_by' => $administrator->id],
            );
            $period = FiscalPeriod::query()->updateOrCreate(
                ['fiscal_year_id' => $fiscalYear->id, 'calendar_year' => 2026, 'calendar_month' => 7],
                ['name' => 'July 2026', 'starts_on' => '2026-07-01', 'ends_on' => '2026-07-31', 'calendar_quarter' => 3, 'status' => 'open'],
            );

            $piece = UnitOfMeasure::query()->updateOrCreate(
                ['code' => 'PC'],
                ['name' => 'Piece', 'status' => 'active', 'created_by' => $administrator->id, 'updated_by' => $administrator->id],
            );
            $serviceUnit = UnitOfMeasure::query()->updateOrCreate(
                ['code' => 'JOB'],
                ['name' => 'Job', 'status' => 'active', 'created_by' => $administrator->id, 'updated_by' => $administrator->id],
            );
            $ict = $this->category('ICT', 'ICT Equipment', 'product', $administrator);
            $office = $this->category('OFFICE', 'Office and School Supplies', 'product', $administrator);
            $services = $this->category('SERVICES', 'Technical Services', 'service', $administrator);

            $laptop = $this->product('ICT-LAP-001', 'Business Laptop 15-inch', 'product', $ict->id, $piece->id, '32000.0000', '38500.0000', true, $administrator);
            $printer = $this->product('ICT-PRN-001', 'Multifunction Ink Tank Printer', 'product', $ict->id, $piece->id, '8500.0000', '10500.0000', true, $administrator);
            $paper = $this->product('OFF-PAP-001', 'A4 Copy Paper 80gsm', 'product', $office->id, $piece->id, '185.0000', '230.0000', true, $administrator);
            $installation = $this->product('SVC-INSTALL', 'Network Installation Service', 'service', $services->id, $serviceUnit->id, '0.0000', '7500.0000', false, $administrator);

            Warehouse::query()->updateOrCreate(
                ['code' => 'MAIN'],
                ['name' => 'Main Stockroom', 'address' => 'National Highway, San Fernando, Pampanga', 'status' => 'active', 'created_by' => $administrator->id, 'updated_by' => $administrator->id],
            );
            $deped = $this->customer('CUS-DEPED', 'DepEd Sample National High School', 'government', '30', $administrator);
            $privateCustomer = $this->customer('CUS-PRIVATE', 'Northstar Business Solutions', 'private', '15', $administrator);
            $this->supplier('SUP-ICT', 'Luzon ICT Distribution Corp.', $administrator);
            $this->supplier('SUP-OFFICE', 'Central Office Supplies Trading', $administrator);

            $cash = PaymentMethod::query()->updateOrCreate(
                ['code' => 'CASH'],
                ['name' => 'Cash', 'type' => 'cash', 'status' => 'active', 'created_by' => $administrator->id, 'updated_by' => $administrator->id],
            );
            $bankTransfer = PaymentMethod::query()->updateOrCreate(
                ['code' => 'BANK'],
                ['name' => 'Bank Transfer', 'type' => 'bank_transfer', 'status' => 'active', 'created_by' => $administrator->id, 'updated_by' => $administrator->id],
            );
            $bank = Bank::query()->updateOrCreate(
                ['code' => 'BDO'],
                ['name' => 'BDO Unibank', 'swift_code' => 'BNORPHMM', 'status' => 'active', 'created_by' => $administrator->id, 'updated_by' => $administrator->id],
            );
            $checking = FinancialAccount::query()->updateOrCreate(
                ['code' => 'BANK-BDO-001'],
                ['name' => 'BDO Operating Account', 'type' => FinancialAccountType::BankChecking, 'bank_id' => $bank->id, 'branch_name' => 'San Fernando',
                    'account_number' => '001234567890', 'account_holder_name' => $profile->registered_business_name, 'opening_balance' => '125000.0000',
                    'opening_balance_date' => '2026-07-01', 'current_balance' => '125000.0000', 'active' => true, 'allow_reconciliation' => true,
                    'created_by' => $administrator->id, 'updated_by' => $administrator->id],
            );
            $cashOnHand = FinancialAccount::query()->updateOrCreate(
                ['code' => 'CASH-ON-HAND'],
                ['name' => 'Cash on Hand', 'type' => FinancialAccountType::CashOnHand, 'opening_balance' => '15000.0000',
                    'opening_balance_date' => '2026-07-01', 'current_balance' => '15000.0000', 'active' => true, 'allow_reconciliation' => false,
                    'created_by' => $administrator->id, 'updated_by' => $administrator->id],
            );

            $quotation = Quotation::query()->updateOrCreate(
                ['reference' => 'DEMO-RFQ-001'],
                ['customer_id' => $deped->id, 'quotation_date' => '2026-07-08', 'valid_until' => '2026-08-07', 'customer_name' => $deped->name,
                    'contact_name' => 'Maria Santos', 'billing_address' => $deped->address, 'delivery_address' => $deped->address,
                    'subtotal' => '56500.0000', 'grand_total' => '56500.0000', 'status' => 'draft', 'notes' => 'Sample DepEd ICT requirement.',
                    'created_by' => $administrator->id, 'updated_by' => $administrator->id],
            );
            $quotation->lines()->updateOrCreate(['line_number' => 1], $this->line($laptop, '1.0000', '38500.0000'));
            $quotation->lines()->updateOrCreate(['line_number' => 2], $this->line($printer, '1.0000', '10500.0000'));
            $quotation->lines()->updateOrCreate(['line_number' => 3], $this->line($installation, '1.0000', '7500.0000'));

            $salesOrder = SalesOrder::query()->updateOrCreate(
                ['customer_po_number' => 'DEMO-PO-2026-001'],
                ['customer_id' => $privateCustomer->id, 'order_date' => '2026-07-10', 'promised_delivery_date' => '2026-07-20',
                    'payment_terms' => 15, 'customer_name' => $privateCustomer->name, 'billing_address' => $privateCustomer->address,
                    'delivery_address' => $privateCustomer->address, 'subtotal' => '40799.0000', 'grand_total' => '40799.0000',
                    'status' => 'draft', 'created_by' => $administrator->id, 'updated_by' => $administrator->id],
            );
            $salesOrder->lines()->updateOrCreate(['line_number' => 1], $this->orderLine($laptop, '1.0000', '38500.0000'));
            $salesOrder->lines()->updateOrCreate(['line_number' => 2], $this->orderLine($paper, '10.0000', '229.9000'));

            $invoice = SalesInvoice::query()->updateOrCreate(
                ['customer_id' => $privateCustomer->id, 'invoice_date' => '2026-07-15', 'notes' => 'Sample direct sales invoice.'],
                ['fiscal_period_id' => $period->id, 'due_date' => '2026-07-30', 'customer_name' => $privateCustomer->name,
                    'billing_address' => $privateCustomer->address, 'source_type' => 'direct', 'gross_amount' => '10500.0000',
                    'net_sales_amount' => '10500.0000', 'total_receivable' => '10500.0000', 'balance_due' => '10500.0000',
                    'status' => 'draft', 'created_by' => $administrator->id, 'updated_by' => $administrator->id],
            );
            $invoice->lines()->updateOrCreate(['line_number' => 1], $this->invoiceLine($printer, '1.0000', '10500.0000'));

            CashReceipt::query()->updateOrCreate(
                ['reference_number' => 'DEMO-DEP-001'],
                ['receipt_date' => '2026-07-18', 'fiscal_period_id' => $period->id, 'financial_account_id' => $checking->id,
                    'source_type' => 'owner_capital', 'payer_name' => 'Juan Dela Cruz', 'payment_method_id' => $bankTransfer->id,
                    'gross_receipt' => '50000.0000', 'net_amount_deposited' => '50000.0000', 'notes' => 'Sample draft capital deposit.',
                    'status' => 'draft', 'created_by' => $administrator->id, 'updated_by' => $administrator->id],
            );
            CashDisbursement::query()->updateOrCreate(
                ['reference_number' => 'DEMO-PAY-001'],
                ['disbursement_date' => '2026-07-19', 'fiscal_period_id' => $period->id, 'financial_account_id' => $cashOnHand->id,
                    'source_type' => 'other_approved', 'payee' => 'Sample Office Landlord', 'payment_method_id' => $cash->id,
                    'gross_settlement' => '8500.0000', 'net_cash_out' => '8500.0000', 'notes' => 'Sample draft office rent payment.',
                    'status' => 'draft', 'created_by' => $administrator->id, 'updated_by' => $administrator->id],
            );
        });
    }

    private function category(string $code, string $name, string $type, User $user): Category
    {
        return Category::query()->updateOrCreate(['code' => $code], compact('name', 'type') + ['status' => 'active', 'created_by' => $user->id, 'updated_by' => $user->id]);
    }

    private function product(string $sku, string $name, string $type, int $categoryId, int $unitId, string $cost, string $price, bool $inventory, User $user): ProductService
    {
        return ProductService::query()->updateOrCreate(['sku' => $sku], ['name' => $name, 'description' => $name, 'type' => $type,
            'category_id' => $categoryId, 'unit_of_measure_id' => $unitId, 'default_cost' => $cost, 'selling_price' => $price,
            'reorder_level' => $inventory ? '5.0000' : '0.0000', 'is_inventory' => $inventory, 'status' => 'active',
            'created_by' => $user->id, 'updated_by' => $user->id]);
    }

    private function customer(string $code, string $name, string $type, string $terms, User $user): Customer
    {
        return Customer::query()->updateOrCreate(['code' => $code], ['name' => $name, 'type' => $type, 'address' => 'San Fernando, Pampanga',
            'contact_person' => 'Sample Contact', 'email' => str($code)->lower().'@example.com', 'phone' => '0917-555-0101',
            'payment_terms' => $terms, 'status' => 'active', 'created_by' => $user->id, 'updated_by' => $user->id]);
    }

    private function supplier(string $code, string $name, User $user): Supplier
    {
        return Supplier::query()->updateOrCreate(['code' => $code], ['name' => $name, 'address' => 'Metro Manila',
            'contact_person' => 'Sample Sales Representative', 'email' => str($code)->lower().'@example.com', 'phone' => '02-8555-0100',
            'payment_terms' => 30, 'status' => 'active', 'created_by' => $user->id, 'updated_by' => $user->id]);
    }

    /** @return array<string, int|string> */
    private function line(ProductService $product, string $quantity, string $price): array
    {
        $amount = bcmul($quantity, $price, 4);

        return ['product_service_id' => $product->id, 'item_type' => $product->type, 'sku' => $product->sku,
            'description' => $product->name, 'uom_code' => $product->unitOfMeasure->code, 'uom_name' => $product->unitOfMeasure->name,
            'quantity' => $quantity, 'unit_price' => $price, 'discount_rate' => '0.000000', 'gross_amount' => $amount,
            'discount_amount' => '0.0000', 'net_amount' => $amount];
    }

    /** @return array<string, int|string> */
    private function orderLine(ProductService $product, string $quantity, string $price): array
    {
        $line = $this->line($product, $quantity, $price);
        unset($line['quantity']);

        return $line + [
            'ordered_quantity' => $quantity, 'delivered_quantity' => '0.0000', 'invoiced_quantity' => '0.0000', 'cancelled_quantity' => '0.0000',
        ];
    }

    /** @return array<string, int|string> */
    private function invoiceLine(ProductService $product, string $quantity, string $price): array
    {
        return $this->line($product, $quantity, $price);
    }
}
