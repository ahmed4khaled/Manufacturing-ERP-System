<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Services\CustomerLedgerService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerLedgerController extends Controller
{
    public function index(Request $request, CustomerLedgerService $ledgerService): View
    {
        $entries = CustomerLedgerEntry::query()
            ->with(['customer', 'creator'])
            ->search($request->input('search'))
            ->filterCustomer($request->input('customer_id'))
            ->filterDateFrom($request->input('date_from'))
            ->filterDateTo($request->input('date_to'))
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        $entries = $ledgerService->attachRunningBalances($entries);

        $customers = Customer::query()->orderBy('name')->get(['id', 'name', 'code']);

        return view('customer-ledger.index', compact('entries', 'customers'));
    }
}
