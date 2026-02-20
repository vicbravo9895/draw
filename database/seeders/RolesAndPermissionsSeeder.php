<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // Inspections
            'inspections.view',
            'inspections.create',
            'inspections.edit',
            'inspections.complete',
            'inspections.delete',

            // Companies
            'companies.view',
            'companies.manage',

            // Users
            'users.view',
            'users.manage',

            // Defect tags
            'defect_tags.view',
            'defect_tags.manage',

            // Exports
            'exports.pdf',
            'exports.csv',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create roles and assign permissions
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdmin->givePermissionTo(Permission::all());

        $companyAdmin = Role::firstOrCreate(['name' => 'company_admin', 'guard_name' => 'web']);
        $companyAdmin->givePermissionTo([
            'inspections.view', 'inspections.create', 'inspections.edit', 'inspections.complete', 'inspections.delete',
            'companies.view',
            'users.view', 'users.manage',
            'defect_tags.view', 'defect_tags.manage',
            'exports.pdf', 'exports.csv',
        ]);

        $supervisor = Role::firstOrCreate(['name' => 'supervisor_calidad', 'guard_name' => 'web']);
        $supervisor->givePermissionTo([
            'inspections.view', 'inspections.create', 'inspections.edit', 'inspections.complete',
            'defect_tags.view',
            'exports.pdf', 'exports.csv',
        ]);

        $inspector = Role::firstOrCreate(['name' => 'inspector', 'guard_name' => 'web']);
        $inspector->givePermissionTo([
            'inspections.view', 'inspections.create', 'inspections.edit',
            'defect_tags.view',
        ]);

        $auditor = Role::firstOrCreate(['name' => 'auditor_interno', 'guard_name' => 'web']);
        $auditor->givePermissionTo([
            'inspections.view',
            'companies.view',
            'defect_tags.view',
            'exports.pdf', 'exports.csv',
        ]);
    }
}
