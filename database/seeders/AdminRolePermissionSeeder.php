<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdminRolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::firstOrCreate([
            'name' => 'SuperAdmin',
            'guard_name' => 'web',
        ]);

        $admin = Role::firstOrCreate([
            'name' => 'Admin',
            'guard_name' => 'web',
        ]);

        // Permisos existentes: conservar su asignación a Admin.
        $existingPermissions = [
            'users.index',
            'users.create',
            'users.edit',
            'users.destroy',
            'users.suspend',
            'users.restore',

            'roles.index',
            'roles.create',
            'roles.show',
            'roles.edit',
            'roles.destroy',

            'permissions.index',
            'permissions.create',
            'permissions.show',
            'permissions.edit',
            'permissions.destroy',

            'profile.view',
            'profile.update',
            'profile.changePassword',

            'errors.index',
            'errors.show',
        ];

        // Permisos nuevos: registrar sin asignar automáticamente.
        $newPermissions = [

            // Documentos comunes
            'documents.index',
            'documents.show',
            'documents.create',
            'documents.edit',
            'documents.destroy',

            // Emisión tributaria
            'documents.issue.boleta',
            'documents.issue.factura',
            'documents.issue.notaCredito',
            'documents.issue.notaDebito',

            // Cajas
            'cashs.index',
            'cashs.open',
            'cashs.close',
            'cashs.collect',
            'cashs.cancelPayment',

            // Reportes de caja
            'cashReports.index',
            'cashReports.show',
            'cashReports.export',

             // Pagos cliente
            'payments.customer.index',
            'payments.customer.show',
            'payments.customer.create',
            'payments.customer.edit',
            'payments.customer.destroy',

             // Pagos  proveedore
            'payments.supplier.index',
            'payments.supplier.show',
            'payments.supplier.create',
            'payments.supplier.edit',
            'payments.supplier.destroy',

             // Pagos clientes
            'payments.customers.index',

             // Pagos proveedores
            'payments.suppliers.index',
        
        ];

        foreach (array_unique(array_merge(
            $existingPermissions,
            $newPermissions
        )) as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Mantener la asignación de permisos anteriores.
        $admin->givePermissionTo($existingPermissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
