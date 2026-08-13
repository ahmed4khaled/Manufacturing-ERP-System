<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$order = \App\Models\PurchaseOrder::first();
if ($order) {
    $order->update(['status' => 'approved']);
    echo 'Updated PO ' . $order->purchase_order_number . ' to approved status.' . PHP_EOL;
    echo 'Items count: ' . $order->items->count() . PHP_EOL;
} else {
    echo 'No POs found to update.' . PHP_EOL;
}
