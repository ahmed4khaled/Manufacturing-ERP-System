<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentReceiptStatus;
use App\Enums\ProductionDefectReason;
use App\Enums\ProductionDefectStatus;
use App\Enums\ProductionMaterialTransactionStatus;
use App\Enums\ProductionOrderStatus;
use App\Enums\ProductionReworkStatus;
use App\Enums\SalesInvoiceStatus;
use App\Enums\SalesReturnStatus;
use App\Enums\StockMovementDirection;
use App\Models\Material;
use App\Models\ProductVariant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StatisticsService
{
    public function __construct(
        private readonly CustomerLedgerService $ledger,
        private readonly StockService $stock,
        private readonly MaterialStockService $materialStock,
    ) {}

    public function resolveFilters(array $input): array
    {
        $preset = $input['preset'] ?? 'month';
        $today = CarbonImmutable::today();
        [$from, $to] = match ($preset) {
            'today' => [$today, $today],
            'week' => [$today->startOfWeek(), $today->endOfWeek()],
            'last_3_months' => [$today->subMonths(2)->startOfMonth(), $today->endOfMonth()],
            'year' => [$today->startOfYear(), $today->endOfYear()],
            'custom' => [CarbonImmutable::parse($input['date_from']), CarbonImmutable::parse($input['date_to'])],
            default => [$today->startOfMonth(), $today->endOfMonth()],
        };

        return [
            'preset' => $preset,
            'from' => $from->startOfDay(),
            'to' => $to->endOfDay(),
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'product_id' => $this->nullableInt($input['product_id'] ?? null),
            'worker_id' => $this->nullableInt($input['worker_id'] ?? null),
            'customer_id' => $this->nullableInt($input['customer_id'] ?? null),
            'supplier_id' => $this->nullableInt($input['supplier_id'] ?? null),
            'material_id' => $this->nullableInt($input['material_id'] ?? null),
            'operation_id' => $this->nullableInt($input['operation_id'] ?? null),
        ];
    }

    public function build(array $filters): array
    {
        $sales = $this->sales($filters);
        $customers = $this->customers($filters);
        $collections = $this->collections($filters, $customers['all']);
        $returns = $this->salesReturns($filters, (float) $sales['quantity']);
        $production = $this->production($filters);
        $workers = $this->performance($filters, 'worker');
        $operations = $this->performance($filters, 'operation');
        $materials = $this->materials($filters);
        $defects = $this->defects($filters, (float) $production['totals']['produced']);
        $inventory = $this->inventory($filters, $sales);
        $purchasing = $this->purchasing($filters);

        return [
            'kpis' => [
                'sales' => $sales['total'],
                'sales_invoices' => $sales['count'],
                'average_invoice' => $sales['average'],
                'collections' => $collections['total'],
                'customer_receivables' => $customers['total_outstanding'],
                'purchases' => $purchasing['total'],
                'supplier_payables' => $purchasing['payables'],
                'product_inventory' => $inventory['products']['total_stock'],
                'material_inventory' => $inventory['materials']['stocked_count'],
                'open_production_orders' => $production['totals']['open'],
                'production_completion_rate' => $production['totals']['completion_rate'],
            ],
            'sales' => $sales,
            'customers' => $customers,
            'collections' => $collections,
            'returns' => $returns,
            'production' => $production,
            'workers' => $workers,
            'operations' => $operations,
            'materials' => $materials,
            'defects' => $defects,
            'inventory' => $inventory,
            'purchasing' => $purchasing,
            'alerts' => $this->alerts($customers, $production, $workers, $operations, $materials, $inventory, $purchasing),
            'charts' => $this->charts($sales, $collections, $returns, $production, $workers, $materials, $defects, $purchasing),
        ];
    }

    public function buildPage(string $page, array $filters): array
    {
        if ($page === 'dashboard') {
            return $this->build($filters);
        }

        if ($page === 'sales') {
            $sales = $this->sales($filters);

            return ['sales' => $sales, 'charts' => ['sales' => $this->chart($sales['trend'], 'period', 'value')]];
        }

        if ($page === 'customers') {
            return ['customers' => $this->customers($filters), 'charts' => []];
        }

        if ($page === 'collections') {
            $customers = $this->customers($filters);
            $collections = $this->collections($filters, $customers['all']);

            return ['collections' => $collections, 'charts' => ['collections' => $this->chart($collections['trend'], 'period', 'value')]];
        }

        if ($page === 'purchases') {
            $purchasing = $this->purchasing($filters);

            return ['purchasing' => $purchasing, 'charts' => ['purchasing' => $this->chart($purchasing['trend'], 'period', 'value')]];
        }

        if ($page === 'inventory') {
            $inventory = $this->inventory($filters, $this->sales($filters));

            return ['inventory' => $inventory, 'charts' => ['product_movements' => $inventory['products']['movement_trend']]];
        }

        if ($page === 'materials') {
            $materials = $this->materials($filters);
            $inventory = $this->inventory($filters, $this->sales($filters));

            return [
                'materials' => $materials,
                'inventory' => $inventory,
                'charts' => [
                    'materials' => $this->materialChart($materials),
                    'material_movements' => $inventory['materials']['movement_trend'],
                ],
            ];
        }

        if ($page === 'manufacturing') {
            $production = $this->production($filters);

            return [
                'production' => $production,
                'operations' => $this->performance($filters, 'operation'),
                'charts' => [
                    'production' => $this->chart($production['trend'], 'period', 'value'),
                    'planned_vs_produced' => ['labels' => ['المخطط', 'المنتج'], 'values' => [$production['totals']['planned'], $production['totals']['produced']]],
                ],
            ];
        }

        if ($page === 'workers') {
            $workers = $this->performance($filters, 'worker');

            return ['workers' => $workers, 'charts' => ['workers' => $this->performanceChart($workers)]];
        }

        if ($page === 'defects') {
            $production = $this->production($filters);
            $defects = $this->defects($filters, (float) $production['totals']['produced']);
            $materials = $this->materials($filters);

            return [
                'defects' => $defects,
                'materials' => $materials,
                'charts' => [
                    'defects' => $this->chart($defects['by_reason'], 'label', 'quantity'),
                    'materials' => $this->materialChart($materials),
                ],
            ];
        }

        if ($page === 'returns') {
            $sales = $this->sales($filters);
            $returns = $this->salesReturns($filters, (float) $sales['quantity']);

            return ['returns' => $returns, 'charts' => ['returns' => $this->chart($returns['trend'], 'period', 'value')]];
        }

        throw new \InvalidArgumentException("Unknown statistics page [{$page}].");
    }

    private function sales(array $filters): array
    {
        $summary = (clone $this->salesInvoices($filters))
            ->selectRaw('COUNT(*) AS invoice_count, COALESCE(SUM(si.total_amount), 0) AS total, COALESCE(AVG(si.total_amount), 0) AS average')
            ->first();
        $items = $this->salesItems($filters);
        $quantity = (float) (clone $items)->sum('sii.quantity');
        $productRows = (clone $items)
            ->selectRaw('p.id, p.name, p.code, SUM(sii.quantity) AS quantity, SUM(sii.line_total) AS value')
            ->groupBy('p.id', 'p.name', 'p.code')
            ->get();

        return [
            'total' => (float) $summary->total,
            'count' => (int) $summary->invoice_count,
            'average' => (float) $summary->average,
            'quantity' => $quantity,
            'trend' => (clone $this->salesInvoices($filters))
                ->selectRaw('DATE(si.invoice_date) AS period, SUM(si.total_amount) AS value')
                ->groupByRaw('DATE(si.invoice_date)')
                ->orderBy('period')
                ->get(),
            'top_products_quantity' => $productRows->sortByDesc('quantity')->take(10)->values(),
            'top_products_value' => $productRows->sortByDesc('value')->take(10)->values(),
            'lowest_products' => $productRows->where('quantity', '>', 0)->sortBy('quantity')->take(10)->values(),
            'by_product' => $productRows->sortByDesc('value')->values(),
            'by_variant' => (clone $items)
                ->selectRaw('pv.id, sii.product_name, sii.color_name, sii.size_name, sii.sku, SUM(sii.quantity) AS quantity, SUM(sii.line_total) AS value')
                ->groupBy('pv.id', 'sii.product_name', 'sii.color_name', 'sii.size_name', 'sii.sku')
                ->orderByDesc('value')->limit(15)->get(),
            'by_color' => (clone $items)->selectRaw("COALESCE(sii.color_name, 'غير محدد') AS label, SUM(sii.quantity) AS quantity, SUM(sii.line_total) AS value")
                ->groupBy('sii.color_name')->orderByDesc('value')->get(),
            'by_size' => (clone $items)->selectRaw("COALESCE(sii.size_name, 'غير محدد') AS label, SUM(sii.quantity) AS quantity, SUM(sii.line_total) AS value")
                ->groupBy('sii.size_name')->orderByDesc('value')->get(),
        ];
    }

    private function customers(array $filters): array
    {
        $sales = $this->salesInvoices($filters)
            ->selectRaw('si.customer_id, COUNT(*) AS invoices, SUM(si.total_quantity) AS quantity, SUM(si.total_amount) AS sales')
            ->groupBy('si.customer_id');
        $payments = $this->paymentReceipts($filters)
            ->selectRaw('pr.customer_id, SUM(pr.amount) AS payments')
            ->groupBy('pr.customer_id');
        $query = DB::table('customers as c')
            ->whereNull('c.deleted_at')
            ->leftJoinSub($sales, 'sales', 'sales.customer_id', '=', 'c.id')
            ->leftJoinSub($payments, 'payments', 'payments.customer_id', '=', 'c.id')
            ->when($filters['customer_id'], fn ($q, $id) => $q->where('c.id', $id))
            ->selectRaw('c.id, c.name, c.code, COALESCE(sales.sales, 0) AS sales, COALESCE(sales.quantity, 0) AS quantity, COALESCE(sales.invoices, 0) AS invoices, COALESCE(payments.payments, 0) AS payments')
            ->get();
        $balances = $this->ledger->getBalances($query->pluck('id'));
        $ranking = $query->map(function ($row) use ($balances) {
            $row->outstanding = (float) ($balances[$row->id] ?? 0);
            $row->average_invoice = $row->invoices > 0 ? (float) $row->sales / (int) $row->invoices : 0;

            return $row;
        });

        return [
            'total_outstanding' => (float) $ranking->sum('outstanding'),
            'all' => $ranking,
            'ranking' => $ranking->sortByDesc('sales')->take(15)->values(),
            'top_sales' => $ranking->sortByDesc('sales')->take(10)->values(),
            'top_quantity' => $ranking->sortByDesc('quantity')->take(10)->values(),
            'top_invoices' => $ranking->sortByDesc('invoices')->take(10)->values(),
            'top_outstanding' => $ranking->where('outstanding', '>', 0)->sortByDesc('outstanding')->take(10)->values(),
            'top_payments' => $ranking->sortByDesc('payments')->take(10)->values(),
        ];
    }

    private function collections(array $filters, Collection $customerRanking): array
    {
        $base = $this->paymentReceipts($filters);
        $total = (float) (clone $base)->sum('pr.amount');
        $byMethod = (clone $base)->selectRaw('pr.payment_method, SUM(pr.amount) AS value, COUNT(*) AS count')
            ->groupBy('pr.payment_method')->get()->map(function ($row) {
                $row->label = PaymentMethod::tryFrom($row->payment_method)?->label() ?? $row->payment_method;

                return $row;
            });

        return [
            'total' => $total,
            'by_method' => $byMethod,
            'trend' => (clone $base)->selectRaw('DATE(pr.payment_date) AS period, SUM(pr.amount) AS value')
                ->groupByRaw('DATE(pr.payment_date)')->orderBy('period')->get(),
            'largest' => (clone $base)->join('customers as c', 'c.id', '=', 'pr.customer_id')
                ->select('pr.id', 'pr.receipt_number', 'pr.payment_date', 'pr.amount', 'pr.payment_method', 'c.name as customer_name')
                ->orderByDesc('pr.amount')->limit(10)->get(),
            'top_customers' => $customerRanking->sortByDesc('payments')->take(10)->values(),
        ];
    }

    private function salesReturns(array $filters, float $soldQuantity): array
    {
        $base = $this->returns($filters);
        $summary = (clone $base)->selectRaw('COUNT(*) AS count, COALESCE(SUM(sr.total_quantity), 0) AS quantity, COALESCE(SUM(sr.total_amount), 0) AS value')->first();
        $items = $this->returnItems($filters);

        return [
            'count' => (int) $summary->count,
            'quantity' => (float) $summary->quantity,
            'value' => (float) $summary->value,
            'rate' => $this->rate((float) $summary->quantity, $soldQuantity),
            'trend' => (clone $base)->selectRaw('DATE(sr.return_date) AS period, SUM(sr.total_amount) AS value, SUM(sr.total_quantity) AS quantity')
                ->groupByRaw('DATE(sr.return_date)')->orderBy('period')->get(),
            'top_products' => (clone $items)->selectRaw('p.id, p.name, p.code, SUM(sri.quantity) AS quantity, SUM(sri.line_total) AS value')
                ->groupBy('p.id', 'p.name', 'p.code')->orderByDesc('quantity')->limit(10)->get(),
            'top_customers' => (clone $base)->join('customers as c', 'c.id', '=', 'sr.customer_id')
                ->selectRaw('c.id, c.name, c.code, SUM(sr.total_amount) AS value, SUM(sr.total_quantity) AS quantity, COUNT(*) AS count')
                ->groupBy('c.id', 'c.name', 'c.code')->orderByDesc('value')->limit(10)->get(),
        ];
    }

    private function production(array $filters): array
    {
        $finalOperations = DB::table('production_order_operations')->selectRaw('production_order_id, MAX(sequence) AS final_sequence')->groupBy('production_order_id');
        $assignmentTotals = DB::table('work_assignments as wa')
            ->join('production_order_operations as poo', 'poo.id', '=', 'wa.production_order_operation_id')
            ->joinSub($finalOperations, 'final_ops', fn ($join) => $join->on('final_ops.production_order_id', '=', 'poo.production_order_id')->on('final_ops.final_sequence', '=', 'poo.sequence'))
            ->selectRaw('wa.production_order_id, SUM(wa.produced_quantity) AS produced')
            ->groupBy('wa.production_order_id');
        $reworkTotals = DB::table('production_reworks as rw')
            ->join('production_order_operations as poo', 'poo.id', '=', 'rw.production_order_operation_id')
            ->joinSub($finalOperations, 'final_ops', fn ($join) => $join->on('final_ops.production_order_id', '=', 'poo.production_order_id')->on('final_ops.final_sequence', '=', 'poo.sequence'))
            ->where('rw.status', ProductionReworkStatus::COMPLETED->value)
            ->selectRaw('rw.production_order_id, SUM(rw.good_quantity) AS rework_good')
            ->groupBy('rw.production_order_id');
        $orders = $this->productionOrders($filters)
            ->leftJoinSub($assignmentTotals, 'assignment_totals', 'assignment_totals.production_order_id', '=', 'po.id')
            ->leftJoinSub($reworkTotals, 'rework_totals', 'rework_totals.production_order_id', '=', 'po.id');
        $rows = (clone $orders)->selectRaw('po.id, po.order_number, po.product_name, po.status, po.planned_quantity, COALESCE(assignment_totals.produced, 0) + COALESCE(rework_totals.rework_good, 0) AS produced')
            ->orderByDesc('po.planned_start_date')->get();
        $activeRows = $rows->where('status', '!=', ProductionOrderStatus::CANCELLED->value);
        $planned = (float) $activeRows->sum('planned_quantity');
        $produced = (float) $activeRows->sum('produced');
        $statusCounts = $rows->countBy('status');
        $byProduct = (clone $orders)->where('po.status', '!=', ProductionOrderStatus::CANCELLED->value)
            ->selectRaw('po.product_id, po.product_name, SUM(po.planned_quantity) AS planned, SUM(COALESCE(assignment_totals.produced, 0) + COALESCE(rework_totals.rework_good, 0)) AS produced')
            ->groupBy('po.product_id', 'po.product_name')->orderByDesc('produced')->limit(10)->get()
            ->map(function ($row) {
                $row->completion_rate = $this->rate((float) $row->produced, (float) $row->planned);

                return $row;
            });

        return [
            'totals' => [
                'orders' => $rows->count(),
                'planned' => $planned,
                'produced' => $produced,
                'remaining' => max(0, $planned - $produced),
                'completed' => (int) ($statusCounts[ProductionOrderStatus::COMPLETED->value] ?? 0),
                'in_progress' => (int) (($statusCounts[ProductionOrderStatus::IN_PROGRESS->value] ?? 0) + ($statusCounts[ProductionOrderStatus::PARTIALLY_COMPLETED->value] ?? 0)),
                'on_hold' => (int) ($statusCounts[ProductionOrderStatus::ON_HOLD->value] ?? 0),
                'cancelled' => (int) ($statusCounts[ProductionOrderStatus::CANCELLED->value] ?? 0),
                'open' => $activeRows->whereIn('status', [ProductionOrderStatus::CONFIRMED->value, ProductionOrderStatus::IN_PROGRESS->value, ProductionOrderStatus::PARTIALLY_COMPLETED->value, ProductionOrderStatus::ON_HOLD->value])->count(),
                'completion_rate' => $this->rate($produced, $planned),
            ],
            'orders_attention' => $activeRows->map(function ($row) {
                $row->remaining = max(0, (float) $row->planned_quantity - (float) $row->produced);

                return $row;
            })->where('remaining', '>', 0)->sortByDesc('remaining')->take(10)->values(),
            'planned_vs_produced' => ['planned' => $planned, 'produced' => $produced],
            'trend' => $this->productionOutputs($filters)->selectRaw('DATE(po_out.output_date) AS period, SUM(po_out.quantity) AS value')
                ->groupByRaw('DATE(po_out.output_date)')->orderBy('period')->get(),
            'by_product' => $byProduct,
            'by_operation' => $this->assignmentRows($filters)->selectRaw('poo.manufacturing_operation_id, poo.operation_name, SUM(wa.assigned_quantity) AS assigned, SUM(wa.produced_quantity) AS produced, SUM(wa.good_quantity) AS good, SUM(wa.defective_quantity) AS defective')
                ->groupBy('poo.manufacturing_operation_id', 'poo.operation_name')->orderByDesc('produced')->get(),
        ];
    }

    private function performance(array $filters, string $dimension): array
    {
        $idColumn = $dimension === 'worker' ? 'wa.worker_id' : 'poo.manufacturing_operation_id';
        $assignments = $this->assignmentRows($filters)->selectRaw("{$idColumn} AS dimension_id, SUM(wa.assigned_quantity) AS assigned, SUM(wa.produced_quantity) AS produced, SUM(wa.good_quantity) AS good, SUM(wa.defective_quantity) AS assignment_defects")
            ->groupBy('dimension_id')->get()->keyBy('dimension_id');
        $defectColumn = $dimension === 'worker' ? 'pd.worker_id' : 'poo.manufacturing_operation_id';
        $defects = $this->defectRows($filters)->selectRaw("{$defectColumn} AS dimension_id, SUM(pd.quantity) AS defects")
            ->groupBy('dimension_id')->pluck('defects', 'dimension_id');
        $transactionColumn = $dimension === 'worker' ? 'pmt.worker_id' : 'poo.manufacturing_operation_id';
        $transactions = $this->transactionRows($filters)->selectRaw("{$transactionColumn} AS dimension_id,
                SUM(CASE WHEN pmt.type = 'issue' THEN pmt.quantity ELSE 0 END) AS issued,
                SUM(CASE WHEN pmt.type = 'usage' THEN pmt.quantity ELSE 0 END) AS used,
                SUM(CASE WHEN pmt.type = 'return' THEN pmt.quantity ELSE 0 END) AS returned,
                SUM(CASE WHEN pmt.type = 'waste' THEN pmt.quantity ELSE 0 END) AS waste")
            ->groupBy('dimension_id')->get()->keyBy('dimension_id');
        $reworkColumn = $dimension === 'worker' ? 'rw.worker_id' : 'poo.manufacturing_operation_id';
        $reworks = $this->reworkRows($filters)->selectRaw("{$reworkColumn} AS dimension_id, SUM(rw.assigned_quantity) AS rework, SUM(CASE WHEN rw.status = 'completed' THEN rw.good_quantity ELSE 0 END) AS rework_good")
            ->groupBy('dimension_id')->get()->keyBy('dimension_id');
        $ids = $assignments->keys()->merge($defects->keys())->merge($transactions->keys())->merge($reworks->keys())->unique()->values();
        $names = $dimension === 'worker'
            ? DB::table('workers')->whereIn('id', $ids)->pluck('name', 'id')
            : DB::table('manufacturing_operations')->whereIn('id', $ids)->pluck('name', 'id');
        $rows = $ids->map(function ($id) use ($assignments, $defects, $transactions, $reworks, $names) {
            $assignment = $assignments->get($id);
            $transaction = $transactions->get($id);
            $rework = $reworks->get($id);
            $produced = (float) ($assignment->produced ?? 0);
            $assigned = (float) ($assignment->assigned ?? 0);
            $waste = (float) ($transaction->waste ?? 0);
            $defectQuantity = (float) ($defects[$id] ?? $assignment->assignment_defects ?? 0);

            return (object) [
                'id' => (int) $id,
                'name' => $names[$id] ?? 'غير معروف',
                'assigned' => $assigned,
                'produced' => $produced,
                'good' => (float) ($assignment->good ?? 0),
                'defects' => $defectQuantity,
                'rework' => (float) ($rework->rework ?? 0),
                'issued' => (float) ($transaction->issued ?? 0),
                'used' => (float) ($transaction->used ?? 0),
                'returned' => (float) ($transaction->returned ?? 0),
                'waste' => $waste,
                'defect_rate' => $this->rate($defectQuantity, $produced),
                'waste_rate' => $this->rate($waste, $produced),
                'completion_rate' => $this->rate((float) ($assignment->good ?? 0), $assigned),
            ];
        });

        return [
            'rows' => $rows->sortByDesc('produced')->values(),
            'top_production' => $rows->sortByDesc('produced')->first(),
            'highest_defect_rate' => $rows->where('produced', '>', 0)->sortByDesc('defect_rate')->first(),
            'highest_waste_rate' => $rows->where('produced', '>', 0)->sortByDesc('waste_rate')->first(),
            'highest_rework' => $rows->sortByDesc('rework')->first(),
            'best_completion' => $rows->where('assigned', '>', 0)->sortByDesc('completion_rate')->first(),
        ];
    }

    private function materials(array $filters): array
    {
        $requirements = DB::table('production_order_materials as pom')
            ->join('production_orders as po', 'po.id', '=', 'pom.production_order_id')
            ->whereNull('po.deleted_at')->where('po.status', '!=', ProductionOrderStatus::CANCELLED->value)
            ->whereBetween('po.planned_start_date', [$filters['date_from'], $filters['date_to']])
            ->when($filters['product_id'], fn ($q, $id) => $q->where('po.product_id', $id))
            ->when($filters['material_id'], fn ($q, $id) => $q->where('pom.material_id', $id))
            ->selectRaw('pom.material_id, SUM(pom.required_quantity) AS required')
            ->groupBy('pom.material_id')->pluck('required', 'material_id');
        $allocated = DB::table('work_assignment_materials as wam')
            ->join('production_order_materials as pom', 'pom.id', '=', 'wam.production_order_material_id')
            ->join('production_orders as po', 'po.id', '=', 'pom.production_order_id')
            ->join('work_assignments as wa', 'wa.id', '=', 'wam.work_assignment_id')
            ->join('production_order_operations as poo', 'poo.id', '=', 'wa.production_order_operation_id')
            ->whereNull('po.deleted_at')->where('po.status', '!=', ProductionOrderStatus::CANCELLED->value)
            ->whereBetween('po.planned_start_date', [$filters['date_from'], $filters['date_to']])
            ->when($filters['product_id'], fn ($q, $id) => $q->where('po.product_id', $id))
            ->when($filters['worker_id'], fn ($q, $id) => $q->where('wa.worker_id', $id))
            ->when($filters['operation_id'], fn ($q, $id) => $q->where('poo.manufacturing_operation_id', $id))
            ->when($filters['material_id'], fn ($q, $id) => $q->where('pom.material_id', $id))
            ->selectRaw('pom.material_id, SUM(wam.allocated_quantity) AS allocated')
            ->groupBy('pom.material_id')->pluck('allocated', 'material_id');
        $transactions = $this->transactionRows($filters)->selectRaw("pmt.material_id,
                SUM(CASE WHEN pmt.type = 'issue' THEN pmt.quantity ELSE 0 END) AS issued,
                SUM(CASE WHEN pmt.type = 'usage' THEN pmt.quantity ELSE 0 END) AS used,
                SUM(CASE WHEN pmt.type = 'return' THEN pmt.quantity ELSE 0 END) AS returned,
                SUM(CASE WHEN pmt.type = 'waste' THEN pmt.quantity ELSE 0 END) AS waste")
            ->groupBy('pmt.material_id')->get()->keyBy('material_id');
        $ids = $requirements->keys()->merge($allocated->keys())->merge($transactions->keys())->unique()->values();
        $names = DB::table('materials')->whereIn('id', $ids)->get(['id', 'name', 'code', 'unit'])->keyBy('id');
        $rows = $ids->map(function ($id) use ($requirements, $allocated, $transactions, $names) {
            $transaction = $transactions->get($id);
            $required = (float) ($requirements[$id] ?? 0);
            $used = (float) ($transaction->used ?? 0);
            $waste = (float) ($transaction->waste ?? 0);

            return (object) [
                'id' => (int) $id,
                'name' => $names[$id]->name ?? 'غير معروف',
                'code' => $names[$id]->code ?? null,
                'unit' => $names[$id]->unit ?? '',
                'required' => $required,
                'allocated' => (float) ($allocated[$id] ?? 0),
                'issued' => (float) ($transaction->issued ?? 0),
                'used' => $used,
                'returned' => (float) ($transaction->returned ?? 0),
                'waste' => $waste,
                'variance' => $used + $waste - $required,
                'waste_rate' => $this->rate($waste, $used + $waste),
            ];
        });

        return [
            'totals' => [
                'required' => (float) $rows->sum('required'),
                'allocated' => (float) $rows->sum('allocated'),
                'issued' => (float) $rows->sum('issued'),
                'used' => (float) $rows->sum('used'),
                'returned' => (float) $rows->sum('returned'),
                'waste' => (float) $rows->sum('waste'),
                'waste_rate' => $this->rate((float) $rows->sum('waste'), (float) ($rows->sum('used') + $rows->sum('waste'))),
                'variance' => (float) $rows->sum('variance'),
            ],
            'rows' => $rows->sortByDesc('used')->values(),
            'most_consumed' => $rows->sortByDesc('used')->first(),
            'most_issued' => $rows->sortByDesc('issued')->first(),
            'most_returned' => $rows->sortByDesc('returned')->first(),
            'most_wasted' => $rows->sortByDesc('waste')->first(),
            'highest_waste_rate' => $rows->where(fn ($row) => $row->used + $row->waste > 0)->sortByDesc('waste_rate')->first(),
            'highest_variance' => $rows->sortByDesc(fn ($row) => abs($row->variance))->first(),
        ];
    }

    private function defects(array $filters, float $produced): array
    {
        $base = $this->defectRows($filters);
        $summary = (clone $base)->selectRaw("COUNT(*) AS count, COALESCE(SUM(pd.quantity), 0) AS quantity, COALESCE(SUM(CASE WHEN pd.status = 'scrapped' THEN pd.quantity ELSE 0 END), 0) AS scrap")->first();
        $rework = $this->reworkRows($filters)->selectRaw("COALESCE(SUM(rw.assigned_quantity), 0) AS quantity, COALESCE(SUM(CASE WHEN rw.status = 'completed' THEN rw.good_quantity ELSE 0 END), 0) AS good, SUM(CASE WHEN rw.status = 'completed' THEN 1 ELSE 0 END) AS completed, COUNT(*) AS count")->first();
        $reasonRows = (clone $base)->selectRaw('pd.reason, COUNT(*) AS count, SUM(pd.quantity) AS quantity')->groupBy('pd.reason')->orderByDesc('quantity')->get()->map(function ($row) {
            $row->label = ProductionDefectReason::tryFrom($row->reason)?->label() ?? $row->reason;

            return $row;
        });

        return [
            'count' => (int) $summary->count,
            'quantity' => (float) $summary->quantity,
            'rate' => $this->rate((float) $summary->quantity, $produced),
            'scrap' => (float) $summary->scrap,
            'by_reason' => $reasonRows,
            'by_product' => (clone $base)->selectRaw('po.product_id, po.product_name, SUM(pd.quantity) AS quantity')->groupBy('po.product_id', 'po.product_name')->orderByDesc('quantity')->limit(10)->get(),
            'by_worker' => (clone $base)->join('workers as w', 'w.id', '=', 'pd.worker_id')->selectRaw('w.id, w.name, SUM(pd.quantity) AS quantity')->groupBy('w.id', 'w.name')->orderByDesc('quantity')->limit(10)->get(),
            'by_operation' => (clone $base)->selectRaw('poo.manufacturing_operation_id, poo.operation_name, SUM(pd.quantity) AS quantity')->groupBy('poo.manufacturing_operation_id', 'poo.operation_name')->orderByDesc('quantity')->limit(10)->get(),
            'rework_quantity' => (float) $rework->quantity,
            'rework_good' => (float) $rework->good,
            'rework_completion_rate' => $this->rate((float) $rework->completed, (float) $rework->count),
        ];
    }

    private function inventory(array $filters, array $sales): array
    {
        $variants = ProductVariant::query()->with(['product:id,name,code', 'color:id,name', 'size:id,name'])
            ->when($filters['product_id'], fn ($q, $id) => $q->where('product_id', $id))->get();
        $stocks = $this->stock->getCurrentStocks($variants);
        $productRows = $variants->map(function ($variant) use ($stocks) {
            return (object) [
                'id' => $variant->id,
                'product_id' => $variant->product_id,
                'name' => $variant->product->name,
                'sku' => $variant->sku,
                'variant' => collect([$variant->color?->name, $variant->size?->name])->filter()->join(' / '),
                'stock' => (float) ($stocks[$variant->id] ?? 0),
            ];
        });
        $materials = Material::query()->when($filters['material_id'], fn ($q, $id) => $q->whereKey($id))->get();
        $materialStocks = $this->materialStock->getCurrentStocks($materials);
        $materialRows = $materials->map(function ($material) use ($materialStocks) {
            $stock = (float) ($materialStocks[$material->id] ?? 0);

            return (object) ['id' => $material->id, 'name' => $material->name, 'code' => $material->code, 'unit' => $material->unit->value, 'stock' => $stock, 'minimum' => (float) $material->minimum_stock, 'is_low' => $stock <= (float) $material->minimum_stock];
        });
        $productMovements = DB::table('stock_movements as sm')->join('product_variants as pv', 'pv.id', '=', 'sm.product_variant_id')->join('products as p', 'p.id', '=', 'pv.product_id')
            ->whereNull('sm.deleted_at')->whereBetween('sm.movement_date', [$filters['date_from'], $filters['date_to']])
            ->when($filters['product_id'], fn ($q, $id) => $q->where('pv.product_id', $id));
        $materialMovements = DB::table('material_stock_movements as msm')->join('materials as m', 'm.id', '=', 'msm.material_id')
            ->whereNull('msm.deleted_at')->whereBetween('msm.movement_date', [$filters['date_from'], $filters['date_to']])
            ->when($filters['material_id'], fn ($q, $id) => $q->where('msm.material_id', $id));

        return [
            'products' => [
                'total_stock' => (float) $productRows->sum('stock'),
                'rows' => $productRows->sortBy('stock')->values(),
                'low_stock' => $productRows->where('stock', '<=', 0)->values(),
                'fast_moving' => $sales['top_products_quantity'],
                'slow_moving' => $this->slowProducts($filters),
                'movement_count' => (clone $productMovements)->count(),
                'movement_trend' => $this->movementChart($productMovements, 'sm'),
                'largest_in' => (clone $productMovements)->where('sm.direction', StockMovementDirection::IN->value)->select('sm.*', 'p.name as product_name', 'pv.sku')->orderByDesc('sm.quantity')->first(),
                'largest_out' => (clone $productMovements)->where('sm.direction', StockMovementDirection::OUT->value)->select('sm.*', 'p.name as product_name', 'pv.sku')->orderByDesc('sm.quantity')->first(),
            ],
            'materials' => [
                'stocked_count' => $materialRows->where('stock', '>', 0)->count(),
                'rows' => $materialRows->sortBy('stock')->values(),
                'low_stock' => $materialRows->where('is_low', true)->values(),
                'fast_moving' => (clone $materialMovements)->where('msm.direction', StockMovementDirection::OUT->value)->selectRaw('m.id, m.name, m.code, m.unit, SUM(msm.quantity) AS quantity')->groupBy('m.id', 'm.name', 'm.code', 'm.unit')->orderByDesc('quantity')->limit(10)->get(),
                'slow_moving' => (clone $materialMovements)->where('msm.direction', StockMovementDirection::OUT->value)->selectRaw('m.id, m.name, m.code, m.unit, SUM(msm.quantity) AS quantity')->groupBy('m.id', 'm.name', 'm.code', 'm.unit')->orderBy('quantity')->limit(10)->get(),
                'movement_count' => (clone $materialMovements)->count(),
                'movement_trend' => $this->movementChart($materialMovements, 'msm'),
                'largest_in' => (clone $materialMovements)->where('msm.direction', StockMovementDirection::IN->value)->select('msm.*', 'm.name as material_name', 'm.unit')->orderByDesc('msm.quantity')->first(),
                'largest_out' => (clone $materialMovements)->where('msm.direction', StockMovementDirection::OUT->value)->select('msm.*', 'm.name as material_name', 'm.unit')->orderByDesc('msm.quantity')->first(),
            ],
        ];
    }

    private function purchasing(array $filters): array
    {
        $invoices = DB::table('supplier_invoices as spi')->join('suppliers as s', 's.id', '=', 'spi.supplier_id')
            ->whereBetween('spi.invoice_date', [$filters['date_from'], $filters['date_to']])
            ->when($filters['supplier_id'], fn ($q, $id) => $q->where('spi.supplier_id', $id));
        $purchaseOrders = DB::table('purchase_orders as po')->whereNull('po.deleted_at')->where('po.status', '!=', 'cancelled')
            ->whereBetween('po.order_date', [$filters['date_from'], $filters['date_to']])
            ->when($filters['supplier_id'], fn ($q, $id) => $q->where('po.supplier_id', $id))
            ->when($filters['material_id'], fn ($q, $id) => $q->whereExists(fn ($x) => $x->selectRaw('1')->from('purchase_order_items as poi')->whereColumn('poi.purchase_order_id', 'po.id')->where('poi.material_id', $id)));
        $receiptItems = DB::table('goods_receipt_items as gri')->join('goods_receipts as gr', 'gr.id', '=', 'gri.goods_receipt_id')->join('materials as m', 'm.id', '=', 'gri.material_id')
            ->where('gr.status', 'confirmed')->whereBetween('gr.received_at', [$filters['from'], $filters['to']])
            ->when($filters['supplier_id'], fn ($q, $id) => $q->where('gr.supplier_id', $id))
            ->when($filters['material_id'], fn ($q, $id) => $q->where('gri.material_id', $id));
        $payments = DB::table('supplier_payments as sp')->where('sp.status', 'confirmed')->whereNull('sp.deleted_at')->whereBetween('sp.payment_date', [$filters['date_from'], $filters['date_to']])
            ->when($filters['supplier_id'], fn ($q, $id) => $q->where('sp.supplier_id', $id));
        $returns = DB::table('purchase_returns as pr')->join('purchase_return_items as pri', 'pri.purchase_return_id', '=', 'pr.id')
            ->where('pr.status', 'confirmed')->whereBetween('pr.return_date', [$filters['date_from'], $filters['date_to']])
            ->when($filters['supplier_id'], fn ($q, $id) => $q->where('pr.supplier_id', $id))
            ->when($filters['material_id'], fn ($q, $id) => $q->where('pri.material_id', $id));
        $payables = DB::table('supplier_invoices')->when($filters['supplier_id'], fn ($q, $id) => $q->where('supplier_id', $id))->sum('remaining_amount');

        return [
            'total' => (float) (clone $invoices)->sum('spi.total'),
            'purchase_order_count' => (clone $purchaseOrders)->count(),
            'supplier_invoice_count' => (clone $invoices)->count(),
            'payables' => (float) $payables,
            'payments' => (float) (clone $payments)->sum('sp.amount'),
            'returns' => (float) (clone $returns)->selectRaw('COALESCE(SUM(pri.quantity * pri.unit_cost), 0) AS value')->value('value'),
            'trend' => (clone $invoices)->selectRaw('DATE(spi.invoice_date) AS period, SUM(spi.total) AS value')->groupByRaw('DATE(spi.invoice_date)')->orderBy('period')->get(),
            'top_suppliers' => (clone $invoices)->selectRaw('s.id, s.name, s.code, SUM(spi.total) AS value, COUNT(*) AS transactions')->groupBy('s.id', 's.name', 's.code')->orderByDesc('value')->limit(10)->get(),
            'top_suppliers_transactions' => (clone $invoices)->selectRaw('s.id, s.name, s.code, SUM(spi.total) AS value, COUNT(*) AS transactions')->groupBy('s.id', 's.name', 's.code')->orderByDesc('transactions')->limit(10)->get(),
            'materials' => (clone $receiptItems)->selectRaw('m.id, m.name, m.code, m.unit, SUM(gri.accepted_quantity) AS quantity, SUM(gri.accepted_quantity * gri.unit_cost) AS value, AVG(gri.unit_cost) AS average_cost')->groupBy('m.id', 'm.name', 'm.code', 'm.unit')->orderByDesc('quantity')->limit(10)->get(),
            'expensive_materials' => (clone $receiptItems)->selectRaw('m.id, m.name, m.code, m.unit, AVG(gri.unit_cost) AS average_cost')->groupBy('m.id', 'm.name', 'm.code', 'm.unit')->orderByDesc('average_cost')->limit(10)->get(),
            'supplier_balances' => DB::table('suppliers as s')->leftJoin('supplier_invoices as spi', 'spi.supplier_id', '=', 's.id')->whereNull('s.deleted_at')->when($filters['supplier_id'], fn ($q, $id) => $q->where('s.id', $id))->selectRaw('s.id, s.name, s.code, COALESCE(SUM(spi.remaining_amount), 0) AS outstanding')->groupBy('s.id', 's.name', 's.code')->having('outstanding', '>', 0)->orderByDesc('outstanding')->limit(10)->get(),
        ];
    }

    private function alerts(array $customers, array $production, array $workers, array $operations, array $materials, array $inventory, array $purchasing): array
    {
        return [
            ['type' => 'danger', 'icon' => 'box-seam', 'label' => 'خامات عند أو تحت الحد الأدنى', 'count' => $inventory['materials']['low_stock']->count(), 'items' => $inventory['materials']['low_stock']->take(5)],
            ['type' => 'warning', 'icon' => 'boxes', 'label' => 'متغيرات منتجات نافدة', 'count' => $inventory['products']['low_stock']->count(), 'items' => $inventory['products']['low_stock']->take(5)],
            ['type' => 'warning', 'icon' => 'gear', 'label' => 'أوامر إنتاج بها كميات متبقية', 'count' => $production['orders_attention']->count(), 'items' => $production['orders_attention']->take(5)],
            ['type' => 'info', 'icon' => 'person-exclamation', 'label' => 'أعلى أرصدة عملاء قائمة', 'count' => $customers['top_outstanding']->count(), 'items' => $customers['top_outstanding']->take(5)],
            ['type' => 'info', 'icon' => 'truck', 'label' => 'أعلى أرصدة موردين قائمة', 'count' => $purchasing['supplier_balances']->count(), 'items' => $purchasing['supplier_balances']->take(5)],
            ['type' => 'secondary', 'icon' => 'activity', 'label' => 'أكبر انحراف خامة مسجل', 'count' => $materials['highest_variance'] ? 1 : 0, 'items' => collect(array_filter([$materials['highest_variance']]))],
        ];
    }

    private function charts(array $sales, array $collections, array $returns, array $production, array $workers, array $materials, array $defects, array $purchasing): array
    {
        return [
            'sales' => $this->chart($sales['trend'], 'period', 'value'),
            'collections' => $this->chart($collections['trend'], 'period', 'value'),
            'returns' => $this->chart($returns['trend'], 'period', 'value'),
            'production' => $this->chart($production['trend'], 'period', 'value'),
            'planned_vs_produced' => ['labels' => ['المخطط', 'المنتج'], 'values' => [$production['totals']['planned'], $production['totals']['produced']]],
            'workers' => $this->performanceChart($workers),
            'materials' => $this->materialChart($materials),
            'defects' => $this->chart($defects['by_reason'], 'label', 'quantity'),
            'purchasing' => $this->chart($purchasing['trend'], 'period', 'value'),
        ];
    }

    private function performanceChart(array $performance): array
    {
        $rows = $performance['rows']->take(10);

        return [
            'labels' => $rows->pluck('name')->all(),
            'produced' => $rows->pluck('produced')->map(fn ($value) => (float) $value)->all(),
            'defects' => $rows->pluck('defects')->map(fn ($value) => (float) $value)->all(),
        ];
    }

    private function materialChart(array $materials): array
    {
        $rows = $materials['rows']->take(10);

        return [
            'labels' => $rows->pluck('name')->all(),
            'used' => $rows->pluck('used')->map(fn ($value) => (float) $value)->all(),
            'waste' => $rows->pluck('waste')->map(fn ($value) => (float) $value)->all(),
        ];
    }

    private function movementChart(Builder $query, string $alias): array
    {
        $rows = (clone $query)
            ->selectRaw("DATE({$alias}.movement_date) AS period, {$alias}.direction, SUM({$alias}.quantity) AS value")
            ->groupByRaw("DATE({$alias}.movement_date), {$alias}.direction")
            ->orderBy('period')
            ->get();
        $labels = $rows->pluck('period')->unique()->values();

        return [
            'labels' => $labels->all(),
            'in' => $labels->map(fn ($period) => (float) ($rows->first(fn ($row) => $row->period === $period && $row->direction === StockMovementDirection::IN->value)?->value ?? 0))->all(),
            'out' => $labels->map(fn ($period) => (float) ($rows->first(fn ($row) => $row->period === $period && $row->direction === StockMovementDirection::OUT->value)?->value ?? 0))->all(),
        ];
    }

    private function salesInvoices(array $filters): Builder
    {
        return DB::table('sales_invoices as si')->whereNull('si.deleted_at')->where('si.status', SalesInvoiceStatus::CONFIRMED->value)
            ->whereBetween('si.invoice_date', [$filters['date_from'], $filters['date_to']])
            ->when($filters['customer_id'], fn ($q, $id) => $q->where('si.customer_id', $id))
            ->when($filters['product_id'], fn ($q, $id) => $q->whereExists(fn ($x) => $x->selectRaw('1')->from('sales_invoice_items as filter_items')->join('product_variants as filter_variants', 'filter_variants.id', '=', 'filter_items.product_variant_id')->whereColumn('filter_items.sales_invoice_id', 'si.id')->whereNull('filter_items.deleted_at')->where('filter_variants.product_id', $id)));
    }

    private function salesItems(array $filters): Builder
    {
        return DB::table('sales_invoice_items as sii')->join('sales_invoices as si', 'si.id', '=', 'sii.sales_invoice_id')->join('product_variants as pv', 'pv.id', '=', 'sii.product_variant_id')->join('products as p', 'p.id', '=', 'pv.product_id')
            ->whereNull('sii.deleted_at')->whereNull('si.deleted_at')->where('si.status', SalesInvoiceStatus::CONFIRMED->value)->whereBetween('si.invoice_date', [$filters['date_from'], $filters['date_to']])
            ->when($filters['customer_id'], fn ($q, $id) => $q->where('si.customer_id', $id))->when($filters['product_id'], fn ($q, $id) => $q->where('pv.product_id', $id));
    }

    private function paymentReceipts(array $filters): Builder
    {
        return DB::table('payment_receipts as pr')->whereNull('pr.deleted_at')->where('pr.status', PaymentReceiptStatus::CONFIRMED->value)->whereBetween('pr.payment_date', [$filters['date_from'], $filters['date_to']])->when($filters['customer_id'], fn ($q, $id) => $q->where('pr.customer_id', $id));
    }

    private function returns(array $filters): Builder
    {
        return DB::table('sales_returns as sr')->whereNull('sr.deleted_at')->where('sr.status', SalesReturnStatus::CONFIRMED->value)->whereBetween('sr.return_date', [$filters['date_from'], $filters['date_to']])->when($filters['customer_id'], fn ($q, $id) => $q->where('sr.customer_id', $id))->when($filters['product_id'], fn ($q, $id) => $q->whereExists(fn ($x) => $x->selectRaw('1')->from('sales_return_items as filter_items')->join('product_variants as filter_variants', 'filter_variants.id', '=', 'filter_items.product_variant_id')->whereColumn('filter_items.sales_return_id', 'sr.id')->where('filter_variants.product_id', $id)));
    }

    private function returnItems(array $filters): Builder
    {
        return DB::table('sales_return_items as sri')->join('sales_returns as sr', 'sr.id', '=', 'sri.sales_return_id')->join('product_variants as pv', 'pv.id', '=', 'sri.product_variant_id')->join('products as p', 'p.id', '=', 'pv.product_id')->whereNull('sr.deleted_at')->where('sr.status', SalesReturnStatus::CONFIRMED->value)->whereBetween('sr.return_date', [$filters['date_from'], $filters['date_to']])->when($filters['customer_id'], fn ($q, $id) => $q->where('sr.customer_id', $id))->when($filters['product_id'], fn ($q, $id) => $q->where('pv.product_id', $id));
    }

    private function productionOrders(array $filters): Builder
    {
        return DB::table('production_orders as po')->whereNull('po.deleted_at')->whereBetween('po.planned_start_date', [$filters['date_from'], $filters['date_to']])->when($filters['product_id'], fn ($q, $id) => $q->where('po.product_id', $id));
    }

    private function productionOutputs(array $filters): Builder
    {
        return DB::table('production_outputs as po_out')->join('production_orders as po', 'po.id', '=', 'po_out.production_order_id')->whereNull('po.deleted_at')->where('po.status', '!=', ProductionOrderStatus::CANCELLED->value)->whereBetween('po_out.output_date', [$filters['date_from'], $filters['date_to']])->when($filters['product_id'], fn ($q, $id) => $q->where('po.product_id', $id));
    }

    private function assignmentRows(array $filters): Builder
    {
        return DB::table('work_assignments as wa')->join('production_orders as po', 'po.id', '=', 'wa.production_order_id')->join('production_order_operations as poo', 'poo.id', '=', 'wa.production_order_operation_id')->whereNull('po.deleted_at')->where('po.status', '!=', ProductionOrderStatus::CANCELLED->value)->whereBetween('po.planned_start_date', [$filters['date_from'], $filters['date_to']])->when($filters['product_id'], fn ($q, $id) => $q->where('po.product_id', $id))->when($filters['worker_id'], fn ($q, $id) => $q->where('wa.worker_id', $id))->when($filters['operation_id'], fn ($q, $id) => $q->where('poo.manufacturing_operation_id', $id));
    }

    private function defectRows(array $filters): Builder
    {
        return DB::table('production_defects as pd')->join('production_orders as po', 'po.id', '=', 'pd.production_order_id')->join('production_order_operations as poo', 'poo.id', '=', 'pd.production_order_operation_id')->whereNull('po.deleted_at')->where('po.status', '!=', ProductionOrderStatus::CANCELLED->value)->where('pd.status', '!=', ProductionDefectStatus::REJECTED->value)->whereBetween('po.planned_start_date', [$filters['date_from'], $filters['date_to']])->when($filters['product_id'], fn ($q, $id) => $q->where('po.product_id', $id))->when($filters['worker_id'], fn ($q, $id) => $q->where('pd.worker_id', $id))->when($filters['operation_id'], fn ($q, $id) => $q->where('poo.manufacturing_operation_id', $id));
    }

    private function transactionRows(array $filters): Builder
    {
        return DB::table('production_material_transactions as pmt')->join('production_orders as po', 'po.id', '=', 'pmt.production_order_id')->join('work_assignments as wa', 'wa.id', '=', 'pmt.work_assignment_id')->join('production_order_operations as poo', 'poo.id', '=', 'wa.production_order_operation_id')->whereNull('po.deleted_at')->where('po.status', '!=', ProductionOrderStatus::CANCELLED->value)->where('pmt.status', '!=', ProductionMaterialTransactionStatus::REJECTED->value)->whereBetween('po.planned_start_date', [$filters['date_from'], $filters['date_to']])->when($filters['product_id'], fn ($q, $id) => $q->where('po.product_id', $id))->when($filters['worker_id'], fn ($q, $id) => $q->where('pmt.worker_id', $id))->when($filters['operation_id'], fn ($q, $id) => $q->where('poo.manufacturing_operation_id', $id))->when($filters['material_id'], fn ($q, $id) => $q->where('pmt.material_id', $id));
    }

    private function reworkRows(array $filters): Builder
    {
        return DB::table('production_reworks as rw')->join('production_orders as po', 'po.id', '=', 'rw.production_order_id')->join('production_order_operations as poo', 'poo.id', '=', 'rw.production_order_operation_id')->whereNull('po.deleted_at')->where('po.status', '!=', ProductionOrderStatus::CANCELLED->value)->where('rw.status', '!=', ProductionReworkStatus::CANCELLED->value)->whereBetween('po.planned_start_date', [$filters['date_from'], $filters['date_to']])->when($filters['product_id'], fn ($q, $id) => $q->where('po.product_id', $id))->when($filters['worker_id'], fn ($q, $id) => $q->where('rw.worker_id', $id))->when($filters['operation_id'], fn ($q, $id) => $q->where('poo.manufacturing_operation_id', $id));
    }

    private function slowProducts(array $filters): Collection
    {
        $sales = $this->salesItems($filters)->selectRaw('pv.product_id, SUM(sii.quantity) AS quantity')->groupBy('pv.product_id');

        return DB::table('products as p')->whereNull('p.deleted_at')->leftJoinSub($sales, 'sales', 'sales.product_id', '=', 'p.id')->when($filters['product_id'], fn ($q, $id) => $q->where('p.id', $id))->selectRaw('p.id, p.name, p.code, COALESCE(sales.quantity, 0) AS quantity')->orderBy('quantity')->limit(10)->get();
    }

    private function chart(Collection $rows, string $label, string $value): array
    {
        return ['labels' => $rows->pluck($label)->all(), 'values' => $rows->pluck($value)->map(fn ($item) => (float) $item)->all()];
    }

    private function rate(float $value, float $base): float
    {
        return $base > 0 ? round(($value / $base) * 100, 2) : 0;
    }

    private function nullableInt(mixed $value): ?int
    {
        return filled($value) ? (int) $value : null;
    }
}
