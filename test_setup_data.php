<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo 'Total POs: ' . \App\Models\PurchaseOrder::count() . PHP_EOL;
echo 'Suppliers: ' . \App\Models\Supplier::count() . PHP_EOL;
echo 'Materials: ' . \App\Models\Material::count() . PHP_EOL;

// Create a test PO if database is empty
if (\App\Models\PurchaseOrder::count() == 0) {
    echo 'Creating test data...' . PHP_EOL;
    $supplier = \App\Models\Supplier::first() ?? \App\Models\Supplier::factory()->create();
    $material = \App\Models\Material::first() ?? \App\Models\Material::factory()->create();
    
    $po = \App\Models\PurchaseOrder::create([
        'supplier_id' => $supplier->id,
        'purchase_order_number' => 'PO-TEST-001',
        'order_date' => now(),
        'expected_delivery_date' => now()->addDays(7),
        'status' => 'draft',
        'notes' => 'Test PO',
        'subtotal' => 1000,
        'discount' => 0,
        'tax' => 100,
        'total' => 1100,
    ]);
    
    \App\Models\PurchaseOrderItem::create([
        'purchase_order_id' => $po->id,
        'material_id' => $material->id,
        'quantity' => 10,
        'unit_price' => 100,
        'discount' => 0,
        'tax' => 0,
        'line_total' => 1000,
    ]);
    
    echo 'Created test PO: ' . $po->purchase_order_number . PHP_EOL;
    
    // Now approve it
    $po->update(['status' => 'approved']);
    echo 'Approved PO' . PHP_EOL;
} else {
    $po = \App\Models\PurchaseOrder::first();
    $po->update(['status' => 'approved']);
    echo 'Updated first PO to approved' . PHP_EOL;
}
