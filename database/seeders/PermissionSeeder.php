<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'customers.view',
            'customers.create',
            'customers.edit',
            'customers.delete',
            'categories.view',
            'categories.create',
            'categories.edit',
            'categories.delete',
            'colors.view',
            'colors.create',
            'colors.edit',
            'colors.delete',
            'sizes.view',
            'sizes.create',
            'sizes.edit',
            'sizes.delete',
            'products.view',
            'products.create',
            'products.edit',
            'products.delete',
            'product_variants.view',
            'product_variants.create',
            'product_variants.edit',
            'product_variants.delete',
            'sales_invoices.view',
            'sales_invoices.create',
            'sales_invoices.edit',
            'sales_invoices.delete',
            'sales_invoices.confirm',
            'sales_invoices.cancel',
            'customer_ledger.view',
            'payment_receipts.view',
            'payment_receipts.create',
            'payment_receipts.edit',
            'payment_receipts.delete',
            'payment_receipts.confirm',
            'payment_receipts.cancel',
            'inventory.view',
            'inventory.movements.view',
            'inventory.opening_stock',
            'inventory.adjustment',

            // Materials
            'materials.view',
            'materials.create',
            'materials.edit',
            'materials.delete',

            // Material Inventory
            'material_inventory.view',
            'material_inventory.movements.view',
            'material_inventory.opening_stock',
            'material_inventory.adjustment',

            // Procurement
            'suppliers.view', 'suppliers.create', 'suppliers.update', 'suppliers.delete',
            'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.update', 'purchase_orders.approve', 'purchase_orders.cancel',
            'goods_receipts.view', 'goods_receipts.create', 'goods_receipts.confirm',
            'supplier_invoices.view', 'supplier_invoices.create',
            'supplier_payments.view', 'supplier_payments.create',
            'purchase_returns.view', 'purchase_returns.create', 'purchase_returns.confirm',

            // Manufacturing
            'workers.view', 'workers.create', 'workers.edit', 'workers.delete',
            'manufacturing_operations.view', 'manufacturing_operations.create', 'manufacturing_operations.edit',
            'boms.view', 'boms.create', 'boms.edit', 'boms.activate',
            'production_orders.view', 'production_orders.create', 'production_orders.confirm',
            'production_orders.execute', 'production_orders.review', 'production_orders.complete',
            'production_orders.cancel', 'production_reports.view',

            // Management
            'statistics.view',
            'users.view', 'users.create', 'users.edit', 'users.status',
            'users.assign_roles', 'users.assign_permissions',
            'roles.view', 'roles.create', 'roles.edit', 'roles.delete',
            'permissions.view',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }
}
