<div class="card">
    <div class="card-header bg-white"><h5 class="card-title mb-0">Add Product (Model)</h5></div>
    <div class="card-body">
        <div class="row g-3 align-items-end mb-4">
            <div class="col-md-8">
                <label for="product_selector" class="form-label">Select Model</label>
                <select id="product_selector" class="form-select">
                    <option value="">Choose a model...</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}">{{ $product->name }} @if($product->code)({{ $product->code }})@endif</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <button type="button" class="btn btn-outline-primary w-100" id="loadVariantsBtn" disabled>
                    <i class="bi bi-grid-3x3 me-1"></i> Load Variants
                </button>
            </div>
        </div>

        <div id="matrixEmpty" class="text-center text-muted py-4 border rounded bg-light">
            Select a model to enter quantities by color and size.
        </div>

        <div id="matrixSection" class="d-none">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0">Variant Matrix — <span id="currentProductName" class="text-primary"></span></h6>
                <div class="text-end">
                    <span class="badge bg-primary me-1">Qty: <span id="currentProductQty">0</span></span>
                    <span class="badge bg-success">Total: <span id="currentProductAmount">0.00</span> EGP</span>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle mb-3">
                    <thead class="table-light">
                        <tr>
                            <th>Color</th>
                            <th>Size</th>
                            <th class="text-end">Unit Price</th>
                            <th class="text-end" style="width:120px">Quantity</th>
                        </tr>
                    </thead>
                    <tbody id="variantMatrixBody"></tbody>
                </table>
            </div>

            <button type="button" class="btn btn-success" id="addProductBtn">
                <i class="bi bi-plus-circle me-1"></i> Add Product to Invoice
            </button>
        </div>

        <div id="matrixAlert" class="alert alert-warning mt-3 d-none"></div>
    </div>
</div>
