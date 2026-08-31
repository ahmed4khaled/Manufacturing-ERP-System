<?php

namespace App\Actions\Procurement;

use App\Models\GoodsReceipt;
use App\Models\MaterialStockMovement;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\SupplierInvoice;
use App\Services\MaterialStockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmGoodsReceiptAction
{
    public function __construct(private readonly MaterialStockService $materialStock) {}

    public function handle(GoodsReceipt $receipt): GoodsReceipt
    {
        return DB::transaction(function () use ($receipt): GoodsReceipt {
            $receipt = GoodsReceipt::query()
                ->with(['items.purchaseOrderItem', 'items.supplierInvoiceItem'])
                ->lockForUpdate()
                ->findOrFail($receipt->id);

            if ($receipt->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'لا يمكن تأكيد إلا أذون الاستلام المسودة.']);
            }

            if (MaterialStockMovement::query()
                ->where('reference_type', GoodsReceipt::class)
                ->where('reference_id', $receipt->id)
                ->exists()) {
                throw ValidationException::withMessages(['status' => 'تم ترحيل إذن الاستلام إلى المخزون بالفعل.']);
            }

            $purchaseOrder = $receipt->purchase_order_id
                ? PurchaseOrder::query()->lockForUpdate()->findOrFail($receipt->purchase_order_id)
                : null;
            $supplierInvoice = $receipt->supplier_invoice_id
                ? SupplierInvoice::query()->with('items')->lockForUpdate()->findOrFail($receipt->supplier_invoice_id)
                : null;

            if (! $purchaseOrder && ! $supplierInvoice) {
                throw ValidationException::withMessages(['reference' => 'يجب ربط إذن الاستلام بأمر شراء أو فاتورة مورد.']);
            }

            $purchaseOrderItemIds = $receipt->items->pluck('purchase_order_item_id')->filter()->unique()->values()->all();
            if ($purchaseOrderItemIds !== []) {
                PurchaseOrderItem::query()->whereIn('id', $purchaseOrderItemIds)->lockForUpdate()->get();
            }

            foreach ($receipt->items as $item) {
                $this->validateQuantities($item);

                if ($supplierInvoice) {
                    $this->validateInvoiceItem($receipt, $supplierInvoice, $item);
                } else {
                    $this->validatePurchaseOrderItem($receipt, $item);
                }
            }

            $receipt->update(['status' => 'confirmed']);
            $this->materialStock->createPurchaseReceiptMovements($receipt);

            if ($purchaseOrder) {
                $fullyReceived = $purchaseOrder->items()->get()->every(function ($item): bool {
                    $accepted = (string) $item->receiptItems()
                        ->whereHas('receipt', fn ($query) => $query->where('status', 'confirmed'))
                        ->sum('accepted_quantity');

                    return bccomp($accepted, (string) $item->quantity, 3) >= 0;
                });

                $purchaseOrder->update(['status' => $fullyReceived ? 'fully_received' : 'partially_received']);
            }

            return $receipt->fresh(['items', 'supplierInvoice']);
        });
    }

    private function validateQuantities($item): void
    {
        $acceptedAndRejected = bcadd((string) $item->accepted_quantity, (string) $item->rejected_quantity, 3);
        if (bccomp($acceptedAndRejected, (string) $item->received_quantity, 3) !== 0) {
            throw ValidationException::withMessages(['items' => 'يجب أن يساوي مجموع الكمية المقبولة والمرفوضة الكمية المستلمة.']);
        }
    }

    private function validateInvoiceItem(GoodsReceipt $receipt, SupplierInvoice $invoice, $item): void
    {
        $invoiceItem = $item->supplierInvoiceItem;
        if (! $invoiceItem || (int) $invoiceItem->supplier_invoice_id !== (int) $invoice->id) {
            throw ValidationException::withMessages(['items' => 'يجب أن يتبع كل بند استلام بند فاتورة المورد المرجعية.']);
        }
        if ((int) $receipt->supplier_id !== (int) $invoice->supplier_id || (int) $item->material_id !== (int) $invoiceItem->material_id) {
            throw ValidationException::withMessages(['items' => 'بيانات المورد أو الخامة لا تطابق فاتورة المورد.']);
        }
        if (bccomp((string) $item->ordered_quantity, (string) $invoiceItem->quantity, 3) !== 0
            || bccomp((string) $item->received_quantity, (string) $invoiceItem->quantity, 3) !== 0
            || bccomp((string) $item->accepted_quantity, (string) $invoiceItem->quantity, 3) !== 0
            || bccomp((string) $item->rejected_quantity, '0', 3) !== 0) {
            throw ValidationException::withMessages(['items' => 'الاستلام المباشر يجب أن يكون كاملًا ومطابقًا لكمية الفاتورة.']);
        }
        if (bccomp((string) $item->unit_cost, (string) $invoiceItem->unit_cost, 2) !== 0) {
            throw ValidationException::withMessages(['items' => 'تكلفة إذن الاستلام يجب أن تطابق تكلفة بند فاتورة المورد.']);
        }
    }

    private function validatePurchaseOrderItem(GoodsReceipt $receipt, $item): void
    {
        if (! $item->purchaseOrderItem || (int) $item->purchaseOrderItem->purchase_order_id !== (int) $receipt->purchase_order_id) {
            throw ValidationException::withMessages(['items' => 'يجب أن تتبع بنود الاستلام أمر الشراء المرجعي.']);
        }
        if ((int) $item->material_id !== (int) $item->purchaseOrderItem->material_id) {
            throw ValidationException::withMessages(['items' => 'يجب أن تطابق خامة بند الاستلام خامة بند أمر الشراء.']);
        }

        $acceptedPreviously = (string) $item->purchaseOrderItem->receiptItems()
            ->whereHas('receipt', fn ($query) => $query->where('status', 'confirmed'))
            ->sum('accepted_quantity');
        $newAccepted = bcadd($acceptedPreviously, (string) $item->accepted_quantity, 3);

        if (bccomp($newAccepted, (string) $item->ordered_quantity, 3) > 0) {
            throw ValidationException::withMessages(['items' => 'الكمية المقبولة تتجاوز الكمية المتبقية في أمر الشراء.']);
        }
    }
}
