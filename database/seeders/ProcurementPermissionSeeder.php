<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder; use Spatie\Permission\Models\Permission;
class ProcurementPermissionSeeder extends Seeder { public function run():void { foreach(['suppliers.view','suppliers.create','suppliers.update','suppliers.delete','purchase_orders.view','purchase_orders.create','purchase_orders.update','purchase_orders.approve','purchase_orders.cancel','goods_receipts.view','goods_receipts.create','goods_receipts.confirm','supplier_invoices.view','supplier_invoices.create','supplier_payments.view','supplier_payments.create','purchase_returns.view','purchase_returns.create','purchase_returns.confirm'] as $name) Permission::findOrCreate($name,'web'); } }
