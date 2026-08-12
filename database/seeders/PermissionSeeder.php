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
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }
}