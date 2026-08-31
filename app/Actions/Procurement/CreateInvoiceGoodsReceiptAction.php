<?php

namespace App\Actions\Procurement;

use App\Models\GoodsReceipt;
use App\Models\SupplierInvoice;
use App\Services\CodeGeneratorService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateInvoiceGoodsReceiptAction
{
    public function __construct(
        private readonly CodeGeneratorService $codes,
        private readonly ConfirmGoodsReceiptAction $confirmReceipt,
    ) {}

    public function handle(SupplierInvoice $invoice): GoodsReceipt
    {
        return DB::transaction(function () use ($invoice): GoodsReceipt {
            $invoice = SupplierInvoice::query()
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            $existing = GoodsReceipt::withTrashed()
                ->where('supplier_invoice_id', $invoice->id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if ($existing->trashed()) {
                    throw ValidationException::withMessages([
                        'receipt' => 'إذن الاستلام المرتبط بهذه الفاتورة محفوظ في السجل المحذوف ولا يمكن إنشاء بديل مكرر.',
                    ]);
                }

                if ((int) $invoice->goods_receipt_id !== (int) $existing->id) {
                    $invoice->update(['goods_receipt_id' => $existing->id]);
                }

                return $existing->fresh('items');
            }

            if ($invoice->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'لا يمكن إنشاء إذن استلام لفاتورة لا تحتوي على بنود خامات.',
                ]);
            }

            $receipt = GoodsReceipt::query()->create([
                'receipt_number' => $this->codes->next('GR'),
                'purchase_order_id' => null,
                'supplier_invoice_id' => $invoice->id,
                'supplier_id' => $invoice->supplier_id,
                'received_at' => $invoice->invoice_date,
                'received_by' => auth()->id(),
                'status' => 'draft',
                'notes' => 'استلام مباشر من فاتورة المورد '.$invoice->invoice_number.($invoice->supplier_reference ? ' / مرجع المورد '.$invoice->supplier_reference : ''),
            ]);

            foreach ($invoice->items as $invoiceItem) {
                $receipt->items()->create([
                    'purchase_order_item_id' => null,
                    'supplier_invoice_item_id' => $invoiceItem->id,
                    'material_id' => $invoiceItem->material_id,
                    'ordered_quantity' => $invoiceItem->quantity,
                    'received_quantity' => $invoiceItem->quantity,
                    'accepted_quantity' => $invoiceItem->quantity,
                    'rejected_quantity' => 0,
                    'unit_cost' => $invoiceItem->unit_cost,
                    'notes' => 'استلام كامل تلقائي من الفاتورة',
                ]);
            }

            $this->confirmReceipt->handle($receipt);
            $invoice->update(['goods_receipt_id' => $receipt->id]);

            return $receipt->fresh(['items.material', 'supplierInvoice']);
        });
    }
}
