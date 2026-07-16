<?php

namespace Database\Seeders;

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
        ];
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roles = [
            'Administrator' => $permissions,
            'Owner' => $permissions,
            'Bookkeeper' => ['business-profile.view', 'tax-profile.view', 'tax-profile.update', 'tax-rates.manage', 'fiscal-years.view', 'fiscal-periods.manage', 'fiscal-periods.close', 'document-sequences.view', 'document-sequences.manage', 'document-sequences.issue', 'roles.view', 'system-settings.view', 'customers.view', 'customers.create', 'customers.update', 'suppliers.view', 'suppliers.create', 'suppliers.update'],
            'Encoder' => ['business-profile.view', 'tax-profile.view', 'fiscal-years.view', 'document-sequences.view', 'document-sequences.issue', 'system-settings.view', 'customers.view', 'customers.create', 'customers.update', 'suppliers.view', 'suppliers.create', 'suppliers.update'],
            'Viewer' => ['business-profile.view', 'tax-profile.view', 'fiscal-years.view', 'document-sequences.view', 'roles.view', 'system-settings.view', 'customers.view', 'suppliers.view'],
        ];
        foreach ($roles as $name => $rolePermissions) {
            Role::findOrCreate($name, 'web')->syncPermissions($rolePermissions);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
