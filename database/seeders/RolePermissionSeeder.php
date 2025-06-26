<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */

public function run()
{
    $permissions = [
        'employee-list', 'employee-add', 'employee-edit', 'employee-view',
        'member-list', 'member-add', 'member-edit', 'member-view',
        'receipt-list', 'receipt-add', 'receipt-edit', 'receipt-view',
        'loan-list', 'loan-add', 'loan-edit', 'loan-view',
        'payment-list', 'payment-add', 'payment-edit', 'payment-view',
        'reports',
        'interest-process',
    ];

    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission]);
    }

    $adminRole = Role::firstOrCreate(['name' => 'admin']);
    $adminRole->syncPermissions($permissions);

    $employeeRole = Role::firstOrCreate(['name' => 'employee']);
    $employeeRole->syncPermissions([
        'member-list', 'member-add', 'member-view',
        'loan-list', 'loan-view',
        'receipt-list', 'receipt-view',
        'reports',
    ]);
}

}
