<?php

namespace App\Services\Printing;

use Illuminate\Contracts\View\View;
use InvalidArgumentException;

class DocumentPrinter
{
    /**
     * Render a printable document.
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

            'sales_invoice' => 'printing.sales-invoice',

            'supplier_invoice' => 'printing.supplier-invoice',

            'payment_receipt' => 'printing.payment-receipt',

            'sales_return' => 'printing.sales-return',

            'purchase_order' => 'printing.purchase-order',

            'goods_receipt' => 'printing.goods-receipt',
            'customer_statement' => 'printing.customer_statement',
            'production_order' => 'printing.production-order',
            'material_handover' => 'printing.material-handover',
            default => throw new InvalidArgumentException(
                "نوع المستند غير مدعوم للطباعة: {$type}"
            ),
        };
    }
}
