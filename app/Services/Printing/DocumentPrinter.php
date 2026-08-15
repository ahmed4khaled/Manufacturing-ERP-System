<?php

namespace App\Services\Printing;

use Illuminate\Contracts\View\View;
use InvalidArgumentException;

class DocumentPrinter
{
    /**
     * Render a printable document.
     *
     * @param  mixed  $document
     * @param  string  $type
     * @param  array  $data
     */
    public function render(
        mixed $document,
        string $type,
        array $data = []
    ): View {
        $view = $this->resolveView($type);

        return view($view, [
            'document' => $document,
            ...$data,
        ]);
    }

    /**
     * Resolve the printable view for the document type.
     */
    protected function resolveView(string $type): string
    {
        return match ($type) {

            'sales_invoice' =>
                'printing.sales-invoice',

            'supplier_invoice' =>
                'printing.supplier-invoice',

            'payment_receipt' =>
                'printing.payment-receipt',

            'purchase_order' =>
                'printing.purchase-order',

            'goods_receipt' =>
                'printing.goods-receipt',

            default =>
                throw new InvalidArgumentException(
                    "Unsupported printable document type: {$type}"
                ),
        };
    }
}