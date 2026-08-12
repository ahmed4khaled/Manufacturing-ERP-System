<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'Admin' => Permission::all()->pluck('name')->all(),
            'Purchasing' => [
                'suppliers.view',
                'suppliers.create',
                'suppliers.update',
                'suppliers.delete',
                'purchase_orders.view',
                'purchase_orders.create',
                'purchase_orders.update',
                'purchase_orders.approve',
                'purchase_orders.cancel',
                'goods_receipts.view',
                'goods_receipts.create',
                'goods_receipts.confirm',
                'supplier_invoices.view',
                'supplier_invoices.create',
                'supplier_payments.view',
                'supplier_payments.create',
                'purchase_returns.view',
                'purchase_returns.create',
                'purchase_returns.confirm',
            ],
            'Warehouse' => [
                'inventory.view',
                'inventory.movements.view',
                'inventory.opening_stock',
                'inventory.adjustment',
                'goods_receipts.view',
                'goods_receipts.create',
                'goods_receipts.confirm',
                'purchase_returns.view',
                'purchase_returns.create',
                'purchase_returns.confirm',
                'products.view',
                'products.create',
                'products.edit',
                'products.delete',
                'product_variants.view',
                'product_variants.create',
                'product_variants.edit',
                'product_variants.delete',
            ],
            'Sales' => [
                'customers.view',
                'customers.create',
                'customers.edit',
                'customers.delete',
                'sales_invoices.view',
                'sales_invoices.create',
                'sales_invoices.edit',
                'sales_invoices.delete',
                'sales_invoices.confirm',
                'sales_invoices.cancel',
                'payment_receipts.view',
                'payment_receipts.create',
                'payment_receipts.edit',
                'payment_receipts.delete',
                'payment_receipts.confirm',
                'payment_receipts.cancel',
                'customer_ledger.view',
            ],
            'Accountant' => [
                'supplier_invoices.view',
                'supplier_invoices.create',
                'supplier_payments.view',
                'supplier_payments.create',
                'payment_receipts.view',
                'payment_receipts.create',
                'payment_receipts.confirm',
                'payment_receipts.cancel',
                'customer_ledger.view',
            ],
        ];

        foreach ($roles as $name => $permissionNames) {
            $role = Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            $role->syncPermissions($permissionNames);
        }

        $adminUser = User::where('email', 'test@example.com')->first();
        if ($adminUser) {
            $adminUser->syncRoles(['Admin']);
        }
    }
}
