<?php

namespace App\Actions\Procurement;

use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Services\Accounting\DocumentPostingService;
use App\Services\CodeGeneratorService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateSupplierInvoiceAction
{
    public function __construct(
        private readonly CodeGeneratorService $codes,
        private readonly CreateInvoiceGoodsReceiptAction $createReceipt,
        private readonly DocumentPostingService $accounting,
    ) {}

    public function handle(array $data): SupplierInvoice
    {
        try {
            return DB::transaction(function () use ($data): SupplierInvoice {
                if (! empty($data['submission_token'])) {
                    $existing = SupplierInvoice::query()
                        ->where('submission_token', $data['submission_token'])
                        ->lockForUpdate()
                        ->first();

                    if ($existing) {
                        return $existing->fresh(['items', 'goodsReceipt']);
                    }
                }

                $supplier = Supplier::query()->lockForUpdate()->findOrFail($data['supplier_id']);
                if ($supplier->status !== 'active') {
                    throw ValidationException::withMessages(['supplier_id' => 'لا يمكن إنشاء فاتورة لمورد غير نشط.']);
                }

                $purchaseOrder = $this->purchaseOrder($data, $supplier->id);
                $receipt = $this->existingReceipt($data, $supplier->id, $purchaseOrder?->id);
                $lines = $receipt ? $this->receiptLines($receipt) : $this->requestLines($data);

                if (($data['receipt_mode'] ?? 'later') === 'immediate' && ($purchaseOrder || $receipt)) {
                    throw ValidationException::withMessages(['receipt_mode' => 'الاستلام الفوري مخصص للشراء المباشر غير المرتبط بمستند استلام سابق.']);
                }

                $subtotal = $lines === []
                    ? bcadd((string) ($data['subtotal'] ?? 0), '0', 2)
                    : array_reduce($lines, fn (string $total, array $line) => bcadd($total, $line['line_total'], 2), '0.00');
                $discount = bcadd((string) ($data['discount'] ?? 0), '0', 2);
                $tax = bcadd((string) ($data['tax'] ?? 0), '0', 2);

                if (! $receipt && $lines !== [] && (bccomp($discount, '0', 2) !== 0 || bccomp($tax, '0', 2) !== 0)) {
                    throw ValidationException::withMessages(['discount' => 'يجب تضمين الخصم والضريبة في تكلفة الوحدة الفعلية للشراء المباشر حتى تتطابق الفاتورة وFIFO.']);
                }

                if (bccomp($discount, $subtotal, 2) > 0) {
                    throw ValidationException::withMessages(['discount' => 'لا يمكن أن يتجاوز الخصم الإجمالي الفرعي للفاتورة.']);
                }

                $total = bcadd(bcsub($subtotal, $discount, 2), $tax, 2);
                $advancePaid = $purchaseOrder
                    ? (string) SupplierPayment::query()
                        ->where('purchase_order_id', $purchaseOrder->id)
                        ->where('supplier_id', $supplier->id)
                        ->where('payment_type', 'advance')
                        ->sum('amount')
                    : '0.00';

                if (bccomp($advancePaid, $total, 2) > 0) {
                    throw ValidationException::withMessages(['purchase_order_id' => 'لا يمكن أن تتجاوز الدفعات المقدمة إجمالي الفاتورة.']);
                }

                $remaining = bcsub($total, $advancePaid, 2);
                $status = match (true) {
                    bccomp($remaining, '0', 2) === 0 => 'paid',
                    bccomp($advancePaid, '0', 2) > 0 => 'partially_paid',
                    default => 'unpaid',
                };

                $invoice = SupplierInvoice::query()->create([
                    'invoice_number' => $this->codes->next('PINV'),
                    'supplier_reference' => $data['supplier_reference'] ?? null,
                    'supplier_id' => $supplier->id,
                    'purchase_order_id' => $purchaseOrder?->id,
                    'goods_receipt_id' => $receipt?->id,
                    'receipt_mode' => $data['receipt_mode'] ?? 'later',
                    'submission_token' => $data['submission_token'] ?? null,
                    'invoice_date' => $data['invoice_date'],
                    'due_date' => $data['due_date'] ?? null,
                    'subtotal' => $subtotal,
                    'discount' => $discount,
                    'tax' => $tax,
                    'total' => $total,
                    'remaining_amount' => $remaining,
                    'status' => $status,
                    'notes' => $data['notes'] ?? null,
                ]);

                foreach ($lines as $line) {
                    $invoice->items()->create($line);
                }

                if (($data['receipt_mode'] ?? 'later') === 'immediate') {
                    $this->createReceipt->handle($invoice);
                }

                $this->accounting->supplierInvoice($invoice);

                return $invoice->fresh(['items.material', 'goodsReceipt.items', 'supplier']);
            });
        } catch (QueryException $exception) {
            $existing = ! empty($data['submission_token'])
                ? SupplierInvoice::query()->where('submission_token', $data['submission_token'])->first()
                : null;

            if ($existing) {
                return $existing->load(['items.material', 'goodsReceipt.items', 'supplier']);
            }

            throw $exception;
        }
    }

    private function purchaseOrder(array $data, int $supplierId): ?PurchaseOrder
    {
        if (empty($data['purchase_order_id'])) {
            return null;
        }

        $purchaseOrder = PurchaseOrder::query()->lockForUpdate()->findOrFail($data['purchase_order_id']);
        if ((int) $purchaseOrder->supplier_id !== $supplierId) {
            throw ValidationException::withMessages(['purchase_order_id' => 'يجب أن يطابق مورد أمر الشراء مورد الفاتورة.']);
        }

        return $purchaseOrder;
    }

    private function existingReceipt(array $data, int $supplierId, ?int $purchaseOrderId): ?GoodsReceipt
    {
        if (empty($data['goods_receipt_id'])) {
            return null;
        }

        $receipt = GoodsReceipt::query()->with('items')->where('status', 'confirmed')->lockForUpdate()->findOrFail($data['goods_receipt_id']);
        if ((int) $receipt->supplier_id !== $supplierId) {
            throw ValidationException::withMessages(['goods_receipt_id' => 'يجب أن يطابق مورد إذن الاستلام مورد الفاتورة.']);
        }
        if ($purchaseOrderId && (int) $receipt->purchase_order_id !== $purchaseOrderId) {
            throw ValidationException::withMessages(['goods_receipt_id' => 'يجب أن يطابق أمر الشراء إذن الاستلام المحدد.']);
        }
        if (SupplierInvoice::query()->where('goods_receipt_id', $receipt->id)->exists()) {
            throw ValidationException::withMessages(['goods_receipt_id' => 'تم إنشاء فاتورة مورد لهذا الإذن بالفعل.']);
        }

        return $receipt;
    }

    private function receiptLines(GoodsReceipt $receipt): array
    {
        return $receipt->items->map(fn ($item) => [
            'material_id' => $item->material_id,
            'quantity' => (string) $item->accepted_quantity,
            'unit_cost' => (string) $item->unit_cost,
            'line_total' => bcmul((string) $item->accepted_quantity, (string) $item->unit_cost, 2),
        ])->all();
    }

    private function requestLines(array $data): array
    {
        $lines = collect($data['items'] ?? [])
            ->filter(fn ($line) => filled($line['material_id'] ?? null))
            ->map(function ($line): array {
                $quantity = bcadd((string) ($line['quantity'] ?? 0), '0', 3);
                $unitCost = bcadd((string) ($line['unit_cost'] ?? 0), '0', 2);

                if (bccomp($quantity, '0', 3) <= 0 || bccomp($unitCost, '0', 2) < 0) {
                    throw ValidationException::withMessages(['items' => 'يجب أن تكون كمية الخامة أكبر من صفر وتكلفتها غير سالبة.']);
                }

                return [
                    'material_id' => (int) $line['material_id'],
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'line_total' => bcmul($quantity, $unitCost, 2),
                ];
            })
            ->values()
            ->all();

        if (count(array_unique(array_column($lines, 'material_id'))) !== count($lines)) {
            throw ValidationException::withMessages(['items' => 'لا يجوز تكرار الخامة نفسها داخل الفاتورة.']);
        }

        return $lines;
    }
}
