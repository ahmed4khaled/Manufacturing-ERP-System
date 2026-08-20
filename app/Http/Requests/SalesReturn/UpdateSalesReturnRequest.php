<?php

namespace App\Http\Requests\SalesReturn;

use App\Enums\SalesReturnStatus;

class UpdateSalesReturnRequest extends StoreSalesReturnRequest
{
    public function authorize(): bool
    {
        return $this->route('sales_return')?->status === SalesReturnStatus::DRAFT;
    }
}
