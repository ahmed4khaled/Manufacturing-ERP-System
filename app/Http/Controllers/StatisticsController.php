<?php

namespace App\Http\Controllers;

use App\Http\Requests\StatisticsRequest;
use App\Models\Customer;
use App\Models\ManufacturingOperation;
use App\Models\Material;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Worker;
use App\Services\StatisticsService;
use Illuminate\Contracts\View\View;

class StatisticsController extends Controller
{
    public function dashboard(StatisticsRequest $request, StatisticsService $statistics): View
    {
        return $this->render($request, $statistics, 'dashboard');
    }

    public function sales(StatisticsRequest $request, StatisticsService $statistics): View
    {
        return $this->render($request, $statistics, 'sales', ['product']);
    }

    public function customers(StatisticsRequest $request, StatisticsService $statistics): View
    {
        return $this->render($request, $statistics, 'customers', ['customer']);
    }

    public function collections(StatisticsRequest $request, StatisticsService $statistics): View
    {
        return $this->render($request, $statistics, 'collections', ['customer']);
    }

    public function purchases(StatisticsRequest $request, StatisticsService $statistics): View
    {
        return $this->render($request, $statistics, 'purchases', ['supplier', 'material']);
    }

    public function inventory(StatisticsRequest $request, StatisticsService $statistics): View
    {
        return $this->render($request, $statistics, 'inventory', ['product']);
    }

    public function materials(StatisticsRequest $request, StatisticsService $statistics): View
    {
        return $this->render($request, $statistics, 'materials', ['product', 'worker', 'material', 'operation']);
    }

    public function manufacturing(StatisticsRequest $request, StatisticsService $statistics): View
    {
        return $this->render($request, $statistics, 'manufacturing', ['product', 'operation']);
    }

    public function workers(StatisticsRequest $request, StatisticsService $statistics): View
    {
        return $this->render($request, $statistics, 'workers', ['product', 'worker', 'operation']);
    }

    public function defects(StatisticsRequest $request, StatisticsService $statistics): View
    {
        return $this->render($request, $statistics, 'defects', ['product', 'worker', 'material', 'operation']);
    }

    public function returns(StatisticsRequest $request, StatisticsService $statistics): View
    {
        return $this->render($request, $statistics, 'returns', ['product', 'customer']);
    }

    private function render(StatisticsRequest $request, StatisticsService $statistics, string $page, array $filterFields = []): View
    {
        $filters = $statistics->resolveFilters($request->validated());
        $optionQueries = [
            'product' => fn () => Product::query()->orderBy('name')->get(['id', 'name', 'code']),
            'worker' => fn () => Worker::query()->orderBy('name')->get(['id', 'name', 'code']),
            'customer' => fn () => Customer::query()->orderBy('name')->get(['id', 'name', 'code']),
            'supplier' => fn () => Supplier::query()->orderBy('name')->get(['id', 'name', 'code']),
            'material' => fn () => Material::query()->orderBy('name')->get(['id', 'name', 'code']),
            'operation' => fn () => ManufacturingOperation::query()->orderBy('name')->get(['id', 'name']),
        ];
        $filterOptions = collect($filterFields)
            ->mapWithKeys(fn (string $field) => [(string) str($field)->plural() => $optionQueries[$field]()])
            ->all();

        return view("statistics.{$page}", [
            'statistics' => $statistics->buildPage($page, $filters),
            'filters' => $filters,
            'filterFields' => $filterFields,
            'filterOptions' => $filterOptions,
            'routeName' => "statistics.{$page}",
            'money' => static fn ($value) => number_format((float) $value, 2).' ج.م',
            'number' => static fn ($value, $decimals = 3) => number_format((float) $value, $decimals),
            'percent' => static fn ($value) => number_format((float) $value, 2).'%',
        ]);
    }
}
