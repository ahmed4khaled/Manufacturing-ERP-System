<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Customers
            'customers.view',
            'customers.create',
            'customers.edit',
            'customers.delete',

            // Categories
            'categories.view',
            'categories.create',
            'categories.edit',
            'categories.delete',

            // Colors
            'colors.view',
            'colors.create',
            'colors.edit',
            'colors.delete',

            // Sizes
            'sizes.view',
            'sizes.create',
            'sizes.edit',
            'sizes.delete',

            // Products
            'products.view',
            'products.create',
            'products.edit',
            'products.delete',

            // Product Variants
            'product_variants.view',
            'product_variants.create',
            'product_variants.edit',
            'product_variants.delete',

            // Sales Invoices
            'sales_invoices.view',
            'sales_invoices.create',
            'sales_invoices.edit',
            'sales_invoices.delete',
            'sales_invoices.confirm',
            'sales_invoices.cancel',

            // Customer Ledger
            'customer_ledger.view',

            // Payment Receipts
            'payment_receipts.view',
            'payment_receipts.create',
            'payment_receipts.edit',
            'payment_receipts.delete',
            'payment_receipts.confirm',
            'payment_receipts.cancel',

            // Inventory
            'inventory.view',
            'inventory.movements.view',
            'inventory.opening_stock',
            'inventory.adjustment',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }
}