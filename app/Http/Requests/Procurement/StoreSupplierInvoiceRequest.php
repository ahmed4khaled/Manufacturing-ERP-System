<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSupplierInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Temporarily allow all users to create supplier invoices
        // return $this->user()->can('supplier_invoices.create');
    }

    public function rules(): array
    {
        return [
            'supplier_id' => 'required|exists:suppliers,id',
            'purchase_order_id' => 'nullable|exists:purchase_orders,id',
            'goods_receipt_id' => 'nullable|exists:goods_receipts,id',
            'receipt_mode' => ['nullable', Rule::in(['immediate', 'later'])],
            'submission_token' => ['nullable', 'uuid'],
            'supplier_reference' => ['nullable', 'string', 'max:100'],
            'invoice_date' => 'required|date',
            'due_date' => 'nullable|date',
            'subtotal' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'items' => ['nullable', 'array', 'min:1'],
            'items.*.material_id' => ['required_with:items', 'integer', 'distinct', 'exists:materials,id'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['required_with:items', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $mode = $this->input('receipt_mode', 'later');
            $items = array_filter($this->input('items', []), fn ($item) => filled($item['material_id'] ?? null));

            if ($mode === 'immediate') {
                if ($this->filled('purchase_order_id') || $this->filled('goods_receipt_id')) {
                    $validator->errors()->add('receipt_mode', 'الاستلام الفوري مخصص للشراء المباشر غير المرتبط بأمر أو إذن استلام سابق.');
                }

                if ($items === []) {
                    $validator->errors()->add('items', 'يجب إضافة خامة واحدة على الأقل للاستلام الفوري.');
                }

            }

            if (! $this->filled('goods_receipt_id') && $items !== []
                && (bccomp((string) $this->input('discount', 0), '0', 2) !== 0 || bccomp((string) $this->input('tax', 0), '0', 2) !== 0)) {
                $validator->errors()->add('discount', 'في الشراء المباشر أدخل التكلفة الفعلية داخل سعر الوحدة؛ لا يُسمح بخصم أو ضريبة إجمالية حتى تتطابق الفاتورة وإذن الاستلام وFIFO.');
            }

            if ($mode === 'later' && ! $this->filled('goods_receipt_id') && $items === [] && ! $this->filled('subtotal')) {
                $validator->errors()->add('items', 'أضف بنود الفاتورة أو اربطها بإذن استلام مؤكد.');
            }
        });
    }
}
