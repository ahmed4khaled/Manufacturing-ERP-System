<?php

namespace Database\Seeders;

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
                'expenses.view',
                'expenses.create',
                'expenses.reverse',
                'wages.view',
                'wages.pay',
                'wages.reverse',
                'cutover.view',
                'cutover.create',
                'cutover.import',
                'cutover.post',
                'cutover.reverse',
                'cutover.reconcile',
            ],
            'Manufacturing' => [
                'workers.view', 'manufacturing_operations.view', 'boms.view',
                'production_orders.view', 'production_orders.create', 'production_orders.execute',
                'wages.view',
            ],
            'Production Supervisor' => [
                'workers.view', 'workers.create', 'workers.edit',
                'manufacturing_operations.view', 'manufacturing_operations.create', 'manufacturing_operations.edit',
                'boms.view', 'boms.create', 'boms.edit', 'boms.activate',
                'production_orders.view', 'production_orders.create', 'production_orders.confirm',
                'production_orders.execute', 'production_orders.review', 'production_orders.complete',
                'production_orders.cancel', 'production_reports.view',
                'wages.view', 'wages.create', 'wages.update',
            ],
        ];

        foreach ($roles as $name => $permissionNames) {
            $role = Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            $role->syncPermissions($permissionNames);
        }
    }
}
