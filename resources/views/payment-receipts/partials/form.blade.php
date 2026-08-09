@props([
    'paymentReceipt' => null,
    'customers' => [],
    'formAction' => '',
    'formMethod' => 'POST',
    'submitLabel' => 'Save Receipt',
])

<form method="POST" action="{{ $formAction }}">
    @csrf
    @if ($formMethod !== 'POST')
        @method($formMethod)
    @endif

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-white"><h5 class="card-title mb-0">Receipt Details</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="customer_id" class="form-label">Customer <span class="text-danger">*</span></label>
                            <select name="customer_id" id="customer_id" class="form-select @error('customer_id') is-invalid @enderror" required>
                                <option value="">Select customer</option>
                                @foreach ($customers as $customer)
                                    <option value="{{ $customer->id }}" @selected(old('customer_id', $paymentReceipt?->customer_id) == $customer->id)>
                                        {{ $customer->code }} — {{ $customer->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('customer_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label for="payment_date" class="form-label">Payment Date <span class="text-danger">*</span></label>
                            <input type="date" name="payment_date" id="payment_date" class="form-control @error('payment_date') is-invalid @enderror"
                                value="{{ old('payment_date', $paymentReceipt?->payment_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}" required>
                            @error('payment_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label for="amount" class="form-label">Amount (EGP) <span class="text-danger">*</span></label>
                            <input type="number" name="amount" id="amount" step="0.01" min="0.01" class="form-control @error('amount') is-invalid @enderror"
                                value="{{ old('amount', $paymentReceipt?->amount) }}" required>
                            @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label for="payment_method" class="form-label">Payment Method <span class="text-danger">*</span></label>
                            <select name="payment_method" id="payment_method" class="form-select @error('payment_method') is-invalid @enderror" required>
                                <option value="">Select method</option>
                                @foreach (\App\Enums\PaymentMethod::cases() as $method)
                                    <option value="{{ $method->value }}" @selected(old('payment_method', $paymentReceipt?->payment_method?->value) === $method->value)>
                                        {{ $method->label() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('payment_method')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label for="reference_number" class="form-label">Reference Number</label>
                            <input type="text" name="reference_number" id="reference_number" class="form-control @error('reference_number') is-invalid @enderror"
                                value="{{ old('reference_number', $paymentReceipt?->reference_number) }}" placeholder="Cheque #, transfer ref, etc.">
                            @error('reference_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea name="notes" id="notes" rows="3" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $paymentReceipt?->notes) }}</textarea>
                            @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-white"><h5 class="card-title mb-0">Information</h5></div>
                <div class="card-body">
                    @if ($paymentReceipt)
                        <div class="mb-3">
                            <label class="form-label text-muted small mb-1">Receipt Number</label>
                            <p class="mb-0 fw-semibold">{{ $paymentReceipt->receipt_number }}</p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small mb-1">Status</label>
                            <p class="mb-0">
                                <span class="badge bg-{{ $paymentReceipt->status->badgeClass() }}">{{ $paymentReceipt->status->label() }}</span>
                            </p>
                            <div class="form-text">Use Confirm or Cancel actions on the receipt page to change status.</div>
                        </div>
                    @else
                        <p class="text-muted small mb-0">
                            <i class="bi bi-info-circle me-1"></i>
                            Receipt number will be generated automatically (e.g. RCPT000001) when saved.
                        </p>
                    @endif
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-check-lg me-1"></i> {{ $submitLabel }}
                </button>
                <a href="{{ $paymentReceipt ? route('payment-receipts.show', $paymentReceipt) : route('payment-receipts.index') }}" class="btn btn-outline-secondary btn-lg">Cancel</a>
            </div>
        </div>
    </div>
</form>
