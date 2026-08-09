<?php

namespace App\Http\Controllers;

use App\Actions\Customer\CreateCustomerAction;
use App\Actions\Customer\DeleteCustomerAction;
use App\Actions\Customer\UpdateCustomerAction;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Models\Customer;
use App\Services\CustomerLedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $customers = Customer::query()
            ->search($request->input('search'))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('customers.index', compact('customers'));
    }

    public function create(): View
    {
        return view('customers.create');
    }

    public function store(
        StoreCustomerRequest $request,
        CreateCustomerAction $action,
    ): RedirectResponse {
        $customer = $action->handle($request->validated());

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', 'Customer created successfully.');
    }

    public function show(Customer $customer, CustomerLedgerService $ledgerService): View
    {
        $ledgerSummary = $ledgerService->getSummary($customer->id);
        $recentLedgerEntries = $ledgerService->getRecentEntries($customer->id);
        $recentPaymentReceipts = $customer->paymentReceipts()
            ->latest('payment_date')
            ->latest('id')
            ->limit(10)
            ->get();

        return view('customers.show', compact('customer', 'ledgerSummary', 'recentLedgerEntries', 'recentPaymentReceipts'));
    }

    public function edit(Customer $customer): View
    {
        return view('customers.edit', compact('customer'));
    }

    public function update(
        UpdateCustomerRequest $request,
        Customer $customer,
        UpdateCustomerAction $action,
    ): RedirectResponse {
        $action->handle($customer, $request->validated());

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', 'Customer updated successfully.');
    }

    public function destroy(
        Customer $customer,
        DeleteCustomerAction $action,
    ): RedirectResponse {
        $action->handle($customer);

        return redirect()
            ->route('customers.index')
            ->with('success', 'Customer deleted successfully.');
    }
}
