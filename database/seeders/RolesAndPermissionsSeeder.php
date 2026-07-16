<?php

namespace Database\Seeders;

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
        Permission::query()->whereIn('name', ['business-profile.manage', 'tax-profile.manage', 'system-settings.manage'])->where('guard_name', 'web')->delete();
        $permissions = [
            'business-profile.view', 'business-profile.update', 'tax-profile.view', 'tax-profile.update', 'tax-rates.manage',
            'fiscal-years.view', 'fiscal-years.create', 'fiscal-periods.manage', 'fiscal-periods.close', 'fiscal-periods.lock',
            'document-sequences.view', 'document-sequences.manage', 'document-sequences.issue',
            'users.view', 'users.manage', 'roles.view', 'system-settings.view', 'system-settings.update',
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
        ];
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roles = [
            'Administrator' => $permissions,
            'Owner' => $permissions,
            'Bookkeeper' => ['business-profile.view', 'tax-profile.view', 'tax-profile.update', 'tax-rates.manage', 'fiscal-years.view', 'fiscal-periods.manage', 'fiscal-periods.close', 'document-sequences.view', 'document-sequences.manage', 'document-sequences.issue', 'roles.view', 'system-settings.view', 'customers.view', 'customers.create', 'customers.update', 'suppliers.view', 'suppliers.create', 'suppliers.update', 'units-of-measure.view', 'units-of-measure.create', 'units-of-measure.update', 'categories.view', 'categories.create', 'categories.update', 'products-services.view', 'products-services.create', 'products-services.update', 'brands.view', 'brands.create', 'brands.update', 'warehouses.view', 'warehouses.create', 'warehouses.update', 'payment-methods.view', 'payment-methods.create', 'payment-methods.update', 'banks.view', 'banks.create', 'banks.update', ...SalesWorkflow::PERMISSIONS],
            'Encoder' => ['business-profile.view', 'tax-profile.view', 'fiscal-years.view', 'document-sequences.view', 'document-sequences.issue', 'system-settings.view', 'customers.view', 'customers.create', 'customers.update', 'suppliers.view', 'suppliers.create', 'suppliers.update', 'units-of-measure.view', 'units-of-measure.create', 'units-of-measure.update', 'categories.view', 'categories.create', 'categories.update', 'products-services.view', 'products-services.create', 'products-services.update', 'brands.view', 'brands.create', 'brands.update', 'warehouses.view', 'warehouses.create', 'warehouses.update', 'payment-methods.view', 'payment-methods.create', 'payment-methods.update', 'banks.view', 'banks.create', 'banks.update', ...SalesWorkflow::ENCODER_PERMISSIONS],
            'Viewer' => ['business-profile.view', 'tax-profile.view', 'fiscal-years.view', 'document-sequences.view', 'roles.view', 'system-settings.view', 'customers.view', 'suppliers.view', 'units-of-measure.view', 'categories.view', 'products-services.view', 'brands.view', 'warehouses.view', 'payment-methods.view', 'banks.view', ...SalesWorkflow::VIEW_PERMISSIONS],
        ];
        foreach ($roles as $name => $rolePermissions) {
            Role::findOrCreate($name, 'web')->syncPermissions($rolePermissions);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
