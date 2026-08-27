<?php

use App\Http\Controllers\BomController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ColorController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerLedgerController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ManufacturingOperationController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\MaterialInventoryController;
use App\Http\Controllers\PaymentReceiptController;
use App\Http\Controllers\ProcurementController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductionDefectController;
use App\Http\Controllers\ProductionOrderController;
use App\Http\Controllers\ProductionReportController;
use App\Http\Controllers\ProductVariantController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SalesInvoiceController;
use App\Http\Controllers\SalesReturnController;
use App\Http\Controllers\SizeController;
use App\Http\Controllers\StatisticsController;
use App\Http\Controllers\WorkAssignmentController;
use App\Http\Controllers\WorkerController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('statistics')->name('statistics.')->middleware('permission:statistics.view')->group(function () {
        Route::get('/', [StatisticsController::class, 'dashboard'])->name('dashboard');
        Route::get('sales', [StatisticsController::class, 'sales'])->name('sales');
        Route::get('customers', [StatisticsController::class, 'customers'])->name('customers');
        Route::get('collections', [StatisticsController::class, 'collections'])->name('collections');
        Route::get('purchases', [StatisticsController::class, 'purchases'])->name('purchases');
        Route::get('inventory', [StatisticsController::class, 'inventory'])->name('inventory');
        Route::get('materials', [StatisticsController::class, 'materials'])->name('materials');
        Route::get('manufacturing', [StatisticsController::class, 'manufacturing'])->name('manufacturing');
        Route::get('workers', [StatisticsController::class, 'workers'])->name('workers');
        Route::get('defects', [StatisticsController::class, 'defects'])->name('defects');
        Route::get('returns', [StatisticsController::class, 'returns'])->name('returns');
    });
    Route::resource('workers', WorkerController::class);
    Route::resource('manufacturing-operations', ManufacturingOperationController::class)->except('show');
    Route::post('boms/{bom}/activate', [BomController::class, 'activate'])->name('boms.activate');
    Route::resource('boms', BomController::class);
    Route::get('production-orders/{productionOrder}/print', [ProductionOrderController::class, 'print'])->name('production-orders.print');
    Route::post('production-orders/{productionOrder}/confirm', [ProductionOrderController::class, 'confirm'])->name('production-orders.confirm');
    Route::post('production-orders/{productionOrder}/hold', [ProductionOrderController::class, 'hold'])->name('production-orders.hold');
    Route::post('production-orders/{productionOrder}/resume', [ProductionOrderController::class, 'resume'])->name('production-orders.resume');
    Route::post('production-orders/{productionOrder}/cancel', [ProductionOrderController::class, 'cancel'])->name('production-orders.cancel');
    Route::post('production-orders/{productionOrder}/complete', [ProductionOrderController::class, 'complete'])->name('production-orders.complete');
    Route::post('production-orders/{productionOrder}/outputs', [ProductionOrderController::class, 'output'])->name('production-orders.outputs.store');
    Route::resource('production-orders', ProductionOrderController::class)->only(['index', 'create', 'store', 'show', 'destroy']);
    Route::get('work-assignments/{workAssignment}', [WorkAssignmentController::class, 'show'])->name('work-assignments.show');
    Route::post('work-assignments/{workAssignment}/start', [WorkAssignmentController::class, 'start'])->name('work-assignments.start');
    Route::post('work-assignments/{workAssignment}/complete', [WorkAssignmentController::class, 'complete'])->name('work-assignments.complete');
    Route::post('work-assignments/{workAssignment}/defects', [WorkAssignmentController::class, 'defect'])->name('work-assignments.defects.store');
    Route::post('work-assignment-materials/{allocation}/issue', [WorkAssignmentController::class, 'issue'])->name('work-assignment-materials.issue');
    Route::post('work-assignment-materials/{allocation}/usage', [WorkAssignmentController::class, 'usage'])->name('work-assignment-materials.usage');
    Route::post('work-assignment-materials/{allocation}/return', [WorkAssignmentController::class, 'returnMaterial'])->name('work-assignment-materials.return');
    Route::post('work-assignment-materials/{allocation}/waste', [WorkAssignmentController::class, 'waste'])->name('work-assignment-materials.waste');
    Route::post('production-material-transactions/{transaction}/approve', [WorkAssignmentController::class, 'approveWaste'])->name('production-material-transactions.approve');
    Route::post('production-material-transactions/{transaction}/reject', [WorkAssignmentController::class, 'rejectWaste'])->name('production-material-transactions.reject');
    Route::get('production-material-transactions/{transaction}/print', [WorkAssignmentController::class, 'printHandover'])->name('production-material-transactions.print');
    Route::post('production-defects/{productionDefect}/approve', [ProductionDefectController::class, 'approve'])->name('production-defects.approve');
    Route::post('production-defects/{productionDefect}/reject', [ProductionDefectController::class, 'reject'])->name('production-defects.reject');
    Route::post('production-defects/{productionDefect}/rework', [ProductionDefectController::class, 'rework'])->name('production-defects.rework');
    Route::post('production-reworks/{productionRework}/start', [ProductionDefectController::class, 'startRework'])->name('production-reworks.start');
    Route::post('production-reworks/{productionRework}/complete', [ProductionDefectController::class, 'completeRework'])->name('production-reworks.complete');
    Route::get('production-reports/material-variance', [ProductionReportController::class, 'materialVariance'])->name('production-reports.material-variance');
    Route::get(
        '/customer-ledger/{customer}/print',
        [CustomerLedgerController::class, 'print']
    )->name('customer-ledger.print');
    Route::get('suppliers', [ProcurementController::class, 'suppliers'])->name('suppliers.index');
    Route::get('suppliers/create', [ProcurementController::class, 'createSupplier'])->name('suppliers.create');
    Route::post('suppliers', [ProcurementController::class, 'storeSupplier'])->name('suppliers.store');
    Route::get('suppliers/{supplier}/ledger', [ProcurementController::class, 'supplierLedger'])
        ->name('suppliers.ledger');
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
    Route::post('purchase-orders/{purchaseOrder}/approve', [ProcurementController::class, 'approvePurchaseOrder'])->name('purchase-orders.approve');
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
    Route::post('purchase-returns', [ProcurementController::class, 'storeReturn'])->name('purchase-returns.store');
    Route::post('purchase-returns/{purchaseReturn}/confirm', [ProcurementController::class, 'confirmReturn'])->name('purchase-returns.confirm');
    Route::get('purchase-returns', [ProcurementController::class, 'returns'])->name('purchase-returns.index');
    Route::get('purchase-returns/create', [ProcurementController::class, 'createReturn'])->name('purchase-returns.create');
    Route::get('purchase-returns/{purchaseReturn}', [ProcurementController::class, 'showReturn'])->name('purchase-returns.show');
    Route::get('sales-invoices/products/{product}/variants', [SalesInvoiceController::class, 'productVariants'])
        ->name('sales-invoices.product-variants');
    Route::post('sales-invoices/{sales_invoice}/confirm', [SalesInvoiceController::class, 'confirm'])
        ->name('sales-invoices.confirm');
    Route::post('sales-invoices/{sales_invoice}/cancel', [SalesInvoiceController::class, 'cancel'])
        ->name('sales-invoices.cancel');
    Route::get(
        '/sales-invoices/{salesInvoice}/print',
        [SalesInvoiceController::class, 'print']
    )->name('sales-invoices.print');
    Route::post('sales-returns/{salesReturn}/confirm', [SalesReturnController::class, 'confirm'])
        ->name('sales-returns.confirm');
    Route::post('sales-returns/{salesReturn}/cancel', [SalesReturnController::class, 'cancel'])
        ->name('sales-returns.cancel');
    Route::get('sales-returns/{salesReturn}/print', [SalesReturnController::class, 'print'])
        ->name('sales-returns.print');
    Route::get(
        '/payment-receipts/{paymentReceipt}/print',
        [PaymentReceiptController::class, 'print']
    )->name('payment-receipts.print');
    Route::get(
        '/supplier-invoices/{supplierInvoice}/print',
        [ProcurementController::class, 'printSupplierInvoice']
    )->name('supplier-invoices.print');
    Route::get(
        '/payment-receipts/customer/{customer}/invoices',
        [PaymentReceiptController::class, 'customerInvoices']
    )->name('payment-receipts.customer-invoices');
    Route::get(
        '/supplier-invoices/{supplierInvoice}',
        [ProcurementController::class, 'showSupplierInvoice']
    )->name('supplier-invoices.show');
    Route::get(
        '/supplier-payments/{supplierPayment}',
        [ProcurementController::class, 'showSupplierPayment']
    )->name('supplier-payments.show-payment');

    Route::get(
        '/supplier-payments/{supplierPayment}/print',
        [ProcurementController::class, 'printSupplierPayment']
    )->name('supplier-payments.print-payment');
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

    Route::get('material-inventory', [MaterialInventoryController::class, 'index'])->name('material-inventory.index');
    Route::get('material-inventory/movements', [MaterialInventoryController::class, 'movements'])->name('material-inventory.movements');
    Route::get('material-inventory/materials/{material}', [MaterialInventoryController::class, 'show'])->name('material-inventory.show');
    Route::get('material-inventory/opening-stock', [MaterialInventoryController::class, 'createOpeningStock'])->name('material-inventory.opening-stock.create');
    Route::post('material-inventory/opening-stock', [MaterialInventoryController::class, 'storeOpeningStock'])->name('material-inventory.opening-stock.store');
    Route::get('material-inventory/adjustments', [MaterialInventoryController::class, 'createAdjustment'])->name('material-inventory.adjustments.create');
    Route::post('material-inventory/adjustments', [MaterialInventoryController::class, 'storeAdjustment'])->name('material-inventory.adjustments.store');

    Route::resource('materials', MaterialController::class);

    Route::resource('customers', CustomerController::class);
    Route::resource('categories', CategoryController::class);
    Route::resource('products', ProductController::class);
    Route::resource('colors', ColorController::class);
    Route::resource('sizes', SizeController::class);
    Route::resource('product-variants', ProductVariantController::class);
    Route::resource('sales-invoices', SalesInvoiceController::class);
    Route::resource('sales-returns', SalesReturnController::class);
    Route::resource('payment-receipts', PaymentReceiptController::class);

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
