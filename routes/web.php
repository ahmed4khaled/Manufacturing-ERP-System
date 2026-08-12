<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ColorController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerLedgerController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductVariantController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PaymentReceiptController;
use App\Http\Controllers\SalesInvoiceController;
use App\Http\Controllers\SizeController;
use App\Http\Controllers\ProcurementController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('suppliers', [ProcurementController::class, 'suppliers'])->name('suppliers.index');
    Route::get('suppliers/create', [ProcurementController::class, 'createSupplier'])->name('suppliers.create');
    Route::post('suppliers', [ProcurementController::class, 'storeSupplier'])->name('suppliers.store');
    Route::get('suppliers/{supplier}', [ProcurementController::class, 'showSupplier'])->name('suppliers.show');
    Route::get('suppliers/{supplier}/edit', [ProcurementController::class, 'editSupplier'])->name('suppliers.edit');
    Route::put('suppliers/{supplier}', [ProcurementController::class, 'updateSupplier'])->name('suppliers.update');
    Route::delete('suppliers/{supplier}', [ProcurementController::class, 'destroySupplier'])->name('suppliers.destroy');
    Route::post('suppliers/{supplier}/contacts', [ProcurementController::class, 'storeSupplierContact'])->name('suppliers.contacts.store');
    Route::delete('suppliers/{supplier}/contacts/{contact}', [ProcurementController::class, 'destroySupplierContact'])->name('suppliers.contacts.destroy');
    Route::post('purchase-orders', [ProcurementController::class, 'storePurchaseOrder'])->name('purchase-orders.store');
    Route::get('purchase-orders', [ProcurementController::class, 'purchaseOrders'])->name('purchase-orders.index');
    Route::get('purchase-orders/create', [ProcurementController::class, 'createPurchaseOrder'])->name('purchase-orders.create');
    Route::get('purchase-orders/{purchaseOrder}', [ProcurementController::class, 'showPurchaseOrder'])->name('purchase-orders.show');
    Route::post('goods-receipts/{goodsReceipt}/confirm', [ProcurementController::class, 'confirmReceipt'])->name('goods-receipts.confirm');
    Route::post('goods-receipts', [ProcurementController::class, 'storeGoodsReceipt'])->name('goods-receipts.store');
    Route::get('goods-receipts', [ProcurementController::class, 'goodsReceipts'])->name('goods-receipts.index');
    Route::get('goods-receipts/create', [ProcurementController::class, 'createGoodsReceipt'])->name('goods-receipts.create');
    Route::get('goods-receipts/{goodsReceipt}', [ProcurementController::class, 'showGoodsReceipt'])->name('goods-receipts.show');
    Route::post('supplier-invoices', [ProcurementController::class, 'storeInvoice'])->name('supplier-invoices.store');
    Route::get('supplier-invoices', [ProcurementController::class, 'invoices'])->name('supplier-invoices.index');
    Route::get('supplier-invoices/create', [ProcurementController::class, 'createInvoice'])->name('supplier-invoices.create');
    Route::post('supplier-payments', [ProcurementController::class, 'storePayment'])->name('supplier-payments.store');
    Route::get('supplier-payments', [ProcurementController::class, 'payments'])->name('supplier-payments.index');
    Route::get('supplier-payments/create', [ProcurementController::class, 'createPayment'])->name('supplier-payments.create');
    Route::post('purchase-returns/{purchaseReturn}/confirm', [ProcurementController::class, 'confirmReturn'])->name('purchase-returns.confirm');
    Route::get('purchase-returns', [ProcurementController::class, 'returns'])->name('purchase-returns.index');
    Route::get('purchase-returns/create', [ProcurementController::class, 'createReturn'])->name('purchase-returns.create');
    Route::get('sales-invoices/products/{product}/variants', [SalesInvoiceController::class, 'productVariants'])
        ->name('sales-invoices.product-variants');
    Route::post('sales-invoices/{sales_invoice}/confirm', [SalesInvoiceController::class, 'confirm'])
        ->name('sales-invoices.confirm');
    Route::post('sales-invoices/{sales_invoice}/cancel', [SalesInvoiceController::class, 'cancel'])
        ->name('sales-invoices.cancel');

    Route::post('payment-receipts/{payment_receipt}/confirm', [PaymentReceiptController::class, 'confirm'])
        ->name('payment-receipts.confirm');
    Route::post('payment-receipts/{payment_receipt}/cancel', [PaymentReceiptController::class, 'cancel'])
        ->name('payment-receipts.cancel');

    Route::get('customer-ledger', [CustomerLedgerController::class, 'index'])->name('customer-ledger.index');

    Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('inventory/movements', [InventoryController::class, 'movements'])->name('inventory.movements');
    Route::get('inventory/variants/{productVariant}', [InventoryController::class, 'show'])->name('inventory.show');
    Route::get('inventory/opening-stock', [InventoryController::class, 'createOpeningStock'])->name('inventory.opening-stock.create');
    Route::post('inventory/opening-stock', [InventoryController::class, 'storeOpeningStock'])->name('inventory.opening-stock.store');
    Route::get('inventory/adjustments', [InventoryController::class, 'createAdjustment'])->name('inventory.adjustments.create');
    Route::post('inventory/adjustments', [InventoryController::class, 'storeAdjustment'])->name('inventory.adjustments.store');

    Route::resource('customers', CustomerController::class);
    Route::resource('categories', CategoryController::class);
    Route::resource('products', ProductController::class);
    Route::resource('colors', ColorController::class);
    Route::resource('sizes', SizeController::class);
    Route::resource('product-variants', ProductVariantController::class);
    Route::resource('sales-invoices', SalesInvoiceController::class);
    Route::resource('payment-receipts', PaymentReceiptController::class);

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
