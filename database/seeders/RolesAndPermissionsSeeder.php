<?php

namespace Database\Seeders;

use App\Services\Bir1701qPreparation;
use App\Services\Bir2551qPreparation;
use App\Services\BooksAndSchedules;
use App\Services\SalesTaxReconciliation;
use App\Services\TaxComplianceCalendar;
use App\Services\TaxDashboardReviewPack;
use App\Services\TaxFilingHistory;
use App\Services\TaxRuleRegistry;
use App\Services\WithholdingCertificateReconciliation;
use App\Support\AccountingWorkflow;
use App\Support\CashBankWorkflow;
use App\Support\FinancialReportingConvention;
use App\Support\InventoryWorkflow;
use App\Support\PurchasingWorkflow;
use App\Support\SalesWorkflow;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::query()->whereIn('name', ['business-profile.manage', 'tax-profile.manage', 'system-settings.manage', 'quotations.issue', 'deliveries.update',
            'bank-reconciliations.view', 'bank-reconciliations.create', 'bank-reconciliations.complete', 'bank-reconciliations.reopen',
            'posting-rules.manage'])->where('guard_name', 'web')->delete();
        $permissions = [
            'business-profile.view', 'business-profile.update', 'tax-profile.view', 'tax-profile.update', 'tax-rates.manage',
            'fiscal-years.view', 'fiscal-years.create', 'fiscal-periods.manage', 'fiscal-periods.close', 'fiscal-periods.lock',
            'document-sequences.view', 'document-sequences.manage', 'document-sequences.issue',
            'users.view', 'users.manage', 'roles.view', 'system-settings.view', 'system-settings.update',
            'audit-logs.view', 'audit-logs.export', 'audit-logs.view-sensitive',
            'backup-runs.view',
            'customers.view', 'customers.create', 'customers.update', 'customers.delete',
            'suppliers.view', 'suppliers.create', 'suppliers.update', 'suppliers.delete',
            'units-of-measure.view', 'units-of-measure.create', 'units-of-measure.update', 'units-of-measure.delete',
            'categories.view', 'categories.create', 'categories.update', 'categories.delete',
            'products-services.view', 'products-services.create', 'products-services.update', 'products-services.delete',
            'brands.view', 'brands.create', 'brands.update', 'brands.delete',
            'warehouses.view', 'warehouses.create', 'warehouses.update', 'warehouses.delete',
            'payment-methods.view', 'payment-methods.create', 'payment-methods.update', 'payment-methods.delete',
            'banks.view', 'banks.create', 'banks.update', 'banks.delete',
            ...SalesWorkflow::PERMISSIONS,
            ...PurchasingWorkflow::PERMISSIONS,
            ...CashBankWorkflow::PERMISSIONS,
            ...InventoryWorkflow::PERMISSIONS,
            ...AccountingWorkflow::PERMISSIONS,
            ...FinancialReportingConvention::PERMISSIONS,
            ...TaxRuleRegistry::PERMISSIONS,
            ...TaxComplianceCalendar::PERMISSIONS,
            ...SalesTaxReconciliation::PERMISSIONS,
            ...Bir2551qPreparation::PERMISSIONS,
            ...Bir1701qPreparation::PERMISSIONS,
            ...WithholdingCertificateReconciliation::PERMISSIONS,
            ...BooksAndSchedules::PERMISSIONS,
            ...TaxFilingHistory::PERMISSIONS,
            ...TaxDashboardReviewPack::PERMISSIONS,
        ];
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roles = [
            'Administrator' => $permissions,
            'Owner' => $permissions,
            'Bookkeeper' => ['business-profile.view', 'tax-profile.view', 'tax-profile.update', 'tax-rates.manage', 'fiscal-years.view', 'fiscal-periods.manage', 'fiscal-periods.close', 'document-sequences.view', 'document-sequences.manage', 'document-sequences.issue', 'roles.view', 'system-settings.view', 'customers.view', 'customers.create', 'customers.update', 'suppliers.view', 'suppliers.create', 'suppliers.update', 'units-of-measure.view', 'units-of-measure.create', 'units-of-measure.update', 'categories.view', 'categories.create', 'categories.update', 'products-services.view', 'products-services.create', 'products-services.update', 'brands.view', 'brands.create', 'brands.update', 'warehouses.view', 'warehouses.create', 'warehouses.update', 'payment-methods.view', 'payment-methods.create', 'payment-methods.update', 'banks.view', 'banks.create', 'banks.update', ...SalesWorkflow::PERMISSIONS, ...PurchasingWorkflow::PERMISSIONS, ...CashBankWorkflow::PERMISSIONS, ...InventoryWorkflow::PERMISSIONS, ...AccountingWorkflow::PERMISSIONS, ...FinancialReportingConvention::PERMISSIONS, ...SalesTaxReconciliation::PERMISSIONS, ...Bir2551qPreparation::PERMISSIONS, ...Bir1701qPreparation::PERMISSIONS],
            'Encoder' => ['business-profile.view', 'tax-profile.view', 'fiscal-years.view', 'document-sequences.view', 'document-sequences.issue', 'system-settings.view', 'customers.view', 'customers.create', 'customers.update', 'suppliers.view', 'suppliers.create', 'suppliers.update', 'units-of-measure.view', 'units-of-measure.create', 'units-of-measure.update', 'categories.view', 'categories.create', 'categories.update', 'products-services.view', 'products-services.create', 'products-services.update', 'brands.view', 'brands.create', 'brands.update', 'warehouses.view', 'warehouses.create', 'warehouses.update', 'payment-methods.view', 'payment-methods.create', 'payment-methods.update', 'banks.view', 'banks.create', 'banks.update', ...SalesWorkflow::ENCODER_PERMISSIONS, ...PurchasingWorkflow::ENCODER_PERMISSIONS, ...CashBankWorkflow::ENCODER_PERMISSIONS, ...InventoryWorkflow::ENCODER_PERMISSIONS, ...AccountingWorkflow::ENCODER_PERMISSIONS, ...FinancialReportingConvention::VIEW_PERMISSIONS],
            'Viewer' => ['business-profile.view', 'tax-profile.view', 'fiscal-years.view', 'document-sequences.view', 'roles.view', 'system-settings.view', 'customers.view', 'suppliers.view', 'units-of-measure.view', 'categories.view', 'products-services.view', 'brands.view', 'warehouses.view', 'payment-methods.view', 'banks.view', ...SalesWorkflow::VIEW_PERMISSIONS, ...PurchasingWorkflow::VIEW_PERMISSIONS, ...CashBankWorkflow::VIEW_PERMISSIONS, ...InventoryWorkflow::VIEW_PERMISSIONS, ...AccountingWorkflow::VIEW_PERMISSIONS, ...FinancialReportingConvention::VIEW_PERMISSIONS, 'tax-reconciliation.view', 'tax-reconciliation.export', 'bir-2551q.view', 'bir-2551q.export', 'bir-1701q.view', 'bir-1701q.export'],
        ];
        $roles['Bookkeeper'] = [...$roles['Bookkeeper'], ...WithholdingCertificateReconciliation::PERMISSIONS];
        $roles['Viewer'] = [...$roles['Viewer'], 'withholding-certificates.view', 'withholding-reconciliation.export'];
        $roles['Bookkeeper'] = [...$roles['Bookkeeper'], ...BooksAndSchedules::PERMISSIONS];
        $roles['Viewer'] = [...$roles['Viewer'], 'books-of-accounts.view', 'books-of-accounts.export', 'tax-schedules.view', 'tax-schedules.export'];
        $roles['Bookkeeper'] = [...$roles['Bookkeeper'], ...TaxFilingHistory::PERMISSIONS];
        $roles['Viewer'] = [...$roles['Viewer'], 'tax-filings.view', 'tax-attachments.view'];
        $roles['Bookkeeper'] = [...$roles['Bookkeeper'], ...TaxDashboardReviewPack::PERMISSIONS];
        $roles['Viewer'] = [...$roles['Viewer'], 'tax-dashboard.view'];
        foreach ($roles as $name => $rolePermissions) {
            Role::findOrCreate($name, 'web')->syncPermissions($rolePermissions);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
