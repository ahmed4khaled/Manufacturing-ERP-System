<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ColorController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerLedgerController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductVariantController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PaymentReceiptController;
use App\Http\Controllers\SalesInvoiceController;
use App\Http\Controllers\SizeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
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