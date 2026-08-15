<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Services\CustomerLedgerService;
use App\Services\Printing\DocumentPrinter;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerLedgerController extends Controller
{
    public function index(
        Request $request,
        CustomerLedgerService $ledgerService
    ): View {
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

        $customers = Customer::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return view(
            'customer-ledger.index',
            compact('entries', 'customers')
        );
    }


    /**
     * Print customer account statement.
     */
    public function print(
        Customer $customer,
        CustomerLedgerService $ledgerService,
        DocumentPrinter $printer
    ): View {
        /*
        |--------------------------------------------------------------------------
        | Get all customer ledger entries
        |--------------------------------------------------------------------------
        */

        $entries = CustomerLedgerEntry::query()
            ->where('customer_id', $customer->id)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();


        /*
        |--------------------------------------------------------------------------
        | Calculate running balances
        |--------------------------------------------------------------------------
        */

        $runningBalance = 0;

        $entries->each(function ($entry) use (&$runningBalance) {

            $runningBalance += (float) $entry->debit;
            $runningBalance -= (float) $entry->credit;

            $entry->running_balance = $runningBalance;
        });


        /*
        |--------------------------------------------------------------------------
        | Statement totals
        |--------------------------------------------------------------------------
        */

        $totalDebit = (float) $entries->sum('debit');

        $totalCredit = (float) $entries->sum('credit');

        $currentBalance = $totalDebit - $totalCredit;


        /*
        |--------------------------------------------------------------------------
        | Render printable statement
        |--------------------------------------------------------------------------
        */

        return $printer->render(
            $customer,
            'customer_statement',
            [
                'entries' => $entries,
                'totalDebit' => $totalDebit,
                'totalCredit' => $totalCredit,
                'currentBalance' => $currentBalance,
            ]
        );
    }
}