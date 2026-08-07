<?php

namespace App\Services;

use App\Models\ProductVariant;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SalesInvoiceItemService
{
    /**
     * @param  array<int, array{product_id: int, variants: array<int, array{product_variant_id: int, quantity: int}>}>  $products
     * @return array{total_quantity: int, subtotal: string, items: Collection<int, SalesInvoiceItem>}
     */
    public function createItems(SalesInvoice $invoice, array $products): array
    {
        $totalQuantity = 0;
        $subtotal = '0.00';
        $createdItems = collect();

        foreach ($products as $productData) {
            $productId = (int) $productData['product_id'];

            foreach ($productData['variants'] as $variantData) {
                $quantity = (int) $variantData['quantity'];

                if ($quantity <= 0) {
                    continue;
                }

                $variant = ProductVariant::query()
                    ->with(['product', 'color', 'size'])
                    ->find($variantData['product_variant_id']);

                if (! $variant || $variant->product_id !== $productId) {
                    throw ValidationException::withMessages([
                        'products' => 'Invalid variant selection for the selected product.',
                    ]);
                }

                $item = $this->createItemFromVariant($invoice, $variant, $quantity);
                $createdItems->push($item);
                $totalQuantity += $quantity;
                $subtotal = bcadd($subtotal, (string) $item->line_total, 2);
            }
        }

        if ($createdItems->isEmpty()) {
            throw ValidationException::withMessages([
                'products' => 'At least one variant must have a quantity greater than zero.',
            ]);
        }

        return [
            'total_quantity' => $totalQuantity,
            'subtotal' => $subtotal,
            'items' => $createdItems,
        ];
    }

    /**
     * @param  array<int, array{product_id: int, variants: array<int, array{product_variant_id: int, quantity: int}>}>  $products
     * @return array{total_quantity: int, subtotal: string, items: Collection<int, SalesInvoiceItem>}
     */
    public function updateItems(SalesInvoice $invoice, array $products): array
    {
        $existingItems = $invoice->items()->get()->keyBy('product_variant_id');
        $retainedVariantIds = [];
        $totalQuantity = 0;
        $subtotal = '0.00';
        $items = collect();

        foreach ($products as $productData) {
            $productId = (int) $productData['product_id'];

            foreach ($productData['variants'] as $variantData) {
                $quantity = (int) $variantData['quantity'];

                if ($quantity <= 0) {
                    continue;
                }

                $variantId = (int) $variantData['product_variant_id'];
                $existingItem = $existingItems->get($variantId);

                if ($existingItem) {
                    $variant = ProductVariant::query()->find($variantId);

                    if (! $variant || $variant->product_id !== $productId) {
                        throw ValidationException::withMessages([
                            'products' => 'Invalid variant selection for the selected product.',
                        ]);
                    }

                    $lineTotal = bcmul((string) $existingItem->unit_price, (string) $quantity, 2);

                    $existingItem->update([
                        'quantity' => $quantity,
                        'line_total' => $lineTotal,
                    ]);

                    $items->push($existingItem->fresh());
                    $retainedVariantIds[] = $variantId;
                    $totalQuantity += $quantity;
                    $subtotal = bcadd($subtotal, $lineTotal, 2);

                    continue;
                }

                $variant = ProductVariant::query()
                    ->with(['product', 'color', 'size'])
                    ->find($variantId);

                if (! $variant || $variant->product_id !== $productId) {
                    throw ValidationException::withMessages([
                        'products' => 'Invalid variant selection for the selected product.',
                    ]);
                }

                $item = $this->createItemFromVariant($invoice, $variant, $quantity);
                $items->push($item);
                $retainedVariantIds[] = $variantId;
                $totalQuantity += $quantity;
                $subtotal = bcadd($subtotal, (string) $item->line_total, 2);
            }
        }

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'products' => 'At least one variant must have a quantity greater than zero.',
            ]);
        }

        $invoice->items()
            ->whereNotIn('product_variant_id', $retainedVariantIds)
            ->delete();

        return [
            'total_quantity' => $totalQuantity,
            'subtotal' => $subtotal,
            'items' => $items,
        ];
    }

    private function createItemFromVariant(
        SalesInvoice $invoice,
        ProductVariant $variant,
        int $quantity,
    ): SalesInvoiceItem {
        $variant->loadMissing(['product', 'color', 'size']);

        $unitPrice = (string) $variant->default_price;
        $lineTotal = bcmul($unitPrice, (string) $quantity, 2);

        return SalesInvoiceItem::create([
            'sales_invoice_id' => $invoice->id,
            'product_variant_id' => $variant->id,
            'product_name' => $variant->product?->name,
            'color_name' => $variant->color?->name,
            'size_name' => $variant->size?->name,
            'sku' => $variant->sku,
            'barcode' => $variant->barcode,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
        ]);
    }
}
