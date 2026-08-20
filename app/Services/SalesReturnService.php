<?php

namespace App\Services;

use App\Enums\SalesReturnStatus;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SalesReturnService
{
    /**
     * @param  array<int, array{sales_invoice_item_id: int, quantity: numeric-string|int|float}>  $lines
     * @param  array<int, SalesReturnStatus>  $countedStatuses
     * @return array{items: array<int, array<string, mixed>>, total_quantity: string, total_amount: string}
     */
    public function prepareItems(
        SalesInvoice $invoice,
        array $lines,
        ?int $excludeReturnId = null,
        array $countedStatuses = [SalesReturnStatus::DRAFT, SalesReturnStatus::CONFIRMED],
    ): array {
        $requested = collect($lines)
            ->filter(fn (array $line) => bccomp((string) ($line['quantity'] ?? '0'), '0', 3) > 0)
            ->groupBy(fn (array $line) => (int) ($line['sales_invoice_item_id'] ?? 0))
            ->map(fn (Collection $itemLines) => $itemLines->reduce(
                fn (string $total, array $line) => bcadd($total, (string) $line['quantity'], 3),
                '0.000',
            ));

        if ($requested->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'يجب إدخال كمية مرتجعة أكبر من صفر لبند واحد على الأقل.',
            ]);
        }

        $invoiceItems = SalesInvoiceItem::query()
            ->where('sales_invoice_id', $invoice->id)
            ->whereIn('id', $requested->keys())
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($invoiceItems->count() !== $requested->count()) {
            throw ValidationException::withMessages([
                'items' => 'بند واحد أو أكثر لا يتبع فاتورة المبيعات المحددة.',
            ]);
        }

        $returnedByItem = SalesReturnItem::query()
            ->join('sales_returns', 'sales_returns.id', '=', 'sales_return_items.sales_return_id')
            ->where('sales_returns.sales_invoice_id', $invoice->id)
            ->whereNull('sales_returns.deleted_at')
            ->whereIn('sales_returns.status', array_map(
                fn (SalesReturnStatus $status) => $status->value,
                $countedStatuses,
            ))
            ->when($excludeReturnId, fn ($query) => $query->where('sales_returns.id', '!=', $excludeReturnId))
            ->groupBy('sales_return_items.sales_invoice_item_id')
            ->selectRaw('sales_return_items.sales_invoice_item_id, SUM(sales_return_items.quantity) as returned_quantity')
            ->pluck('returned_quantity', 'sales_invoice_item_id');

        $preparedItems = [];
        $totalQuantity = '0.000';
        $totalAmount = '0.00';

        foreach ($requested as $invoiceItemId => $quantity) {
            /** @var SalesInvoiceItem $invoiceItem */
            $invoiceItem = $invoiceItems->get($invoiceItemId);
            $alreadyReturned = (string) ($returnedByItem[$invoiceItemId] ?? '0');
            $eligibleQuantity = bcsub((string) $invoiceItem->quantity, $alreadyReturned, 3);

            if (bccomp($quantity, $eligibleQuantity, 3) > 0) {
                throw ValidationException::withMessages([
                    'items' => "لا يمكن إرجاع كمية أكبر من الكمية المتاحة للإرجاع للبند {$invoiceItem->sku}. المتاح: {$eligibleQuantity}.",
                ]);
            }

            $lineTotal = bcmul((string) $invoiceItem->unit_price, $quantity, 2);
            $preparedItems[] = [
                'sales_invoice_item_id' => $invoiceItem->id,
                'product_variant_id' => $invoiceItem->product_variant_id,
                'product_name' => $invoiceItem->product_name,
                'color_name' => $invoiceItem->color_name,
                'size_name' => $invoiceItem->size_name,
                'sku' => $invoiceItem->sku,
                'quantity' => $quantity,
                'unit_price' => $invoiceItem->unit_price,
                'line_total' => $lineTotal,
            ];
            $totalQuantity = bcadd($totalQuantity, $quantity, 3);
            $totalAmount = bcadd($totalAmount, $lineTotal, 2);
        }
        return [
            'items' => $preparedItems,
            'total_quantity' => $totalQuantity,
            'total_amount' => $totalAmount,
        ];
    }

    /** @param  array<int, array<string, mixed>>  $items */
    public function replaceItems(SalesReturn $salesReturn, array $items): void
    {
        $salesReturn->items()->delete();
        $salesReturn->items()->createMany($items);
    }

    /** @return Collection<int, SalesInvoiceItem> */
    public function invoiceItemsWithEligibility(SalesInvoice $invoice, ?int $excludeReturnId = null): Collection
    {
        $invoice->loadMissing('items');

        $returnedByItem = SalesReturnItem::query()
            ->join('sales_returns', 'sales_returns.id', '=', 'sales_return_items.sales_return_id')
            ->where('sales_returns.sales_invoice_id', $invoice->id)
            ->whereNull('sales_returns.deleted_at')
            ->whereIn('sales_returns.status', [
                SalesReturnStatus::DRAFT->value,
                SalesReturnStatus::CONFIRMED->value,
            ])
            ->when($excludeReturnId, fn ($query) => $query->where('sales_returns.id', '!=', $excludeReturnId))
            ->groupBy('sales_return_items.sales_invoice_item_id')
            ->selectRaw('sales_return_items.sales_invoice_item_id, SUM(sales_return_items.quantity) as returned_quantity')
            ->pluck('returned_quantity', 'sales_invoice_item_id');

        return $invoice->items->map(function (SalesInvoiceItem $item) use ($returnedByItem) {
            $item->eligible_return_quantity = bcsub(
                (string) $item->quantity,
                (string) ($returnedByItem[$item->id] ?? '0'),
                3,
            );

            return $item;
        });
    }
}
