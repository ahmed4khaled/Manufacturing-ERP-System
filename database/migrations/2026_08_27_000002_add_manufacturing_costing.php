<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('base_cost_price', 14, 2)->default(0)->after('cost_price');
        });
        DB::table('products')->update(['base_cost_price' => DB::raw('cost_price')]);

        Schema::table('manufacturing_operations', function (Blueprint $table) {
            $table->decimal('default_unit_cost', 14, 2)->default(0)->after('description');
        });
        Schema::table('bom_operations', function (Blueprint $table) {
            $table->decimal('unit_cost', 14, 2)->default(0)->after('operation_name');
        });
        Schema::table('production_order_operations', function (Blueprint $table) {
            $table->decimal('unit_cost', 14, 2)->default(0)->after('operation_name');
            $table->decimal('total_cost', 14, 2)->default(0)->after('unit_cost');
        });
        Schema::table('production_orders', function (Blueprint $table) {
            $table->decimal('base_unit_cost', 14, 2)->default(0)->after('bom_version');
            $table->decimal('operations_unit_cost', 14, 2)->default(0)->after('base_unit_cost');
            $table->decimal('estimated_unit_cost', 14, 2)->default(0)->after('operations_unit_cost');
            $table->decimal('estimated_total_cost', 14, 2)->default(0)->after('estimated_unit_cost');
            $table->decimal('final_unit_cost', 14, 2)->nullable()->after('estimated_total_cost');
            $table->decimal('final_total_cost', 14, 2)->nullable()->after('final_unit_cost');
        });
        Schema::table('production_outputs', function (Blueprint $table) {
            $table->decimal('unit_cost', 14, 2)->default(0)->after('quantity');
            $table->decimal('total_cost', 14, 2)->default(0)->after('unit_cost');
        });

        $this->backfillProductionCosts();
    }

    public function down(): void
    {
        Schema::table('production_outputs', fn (Blueprint $table) => $table->dropColumn(['unit_cost', 'total_cost']));
        Schema::table('production_orders', fn (Blueprint $table) => $table->dropColumn(['base_unit_cost', 'operations_unit_cost', 'estimated_unit_cost', 'estimated_total_cost', 'final_unit_cost', 'final_total_cost']));
        Schema::table('production_order_operations', fn (Blueprint $table) => $table->dropColumn(['unit_cost', 'total_cost']));
        Schema::table('bom_operations', fn (Blueprint $table) => $table->dropColumn('unit_cost'));
        Schema::table('manufacturing_operations', fn (Blueprint $table) => $table->dropColumn('default_unit_cost'));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('base_cost_price'));
    }

    private function backfillProductionCosts(): void
    {
        DB::table('production_orders')->orderBy('id')->chunkById(100, function ($orders): void {
            foreach ($orders as $order) {
                $baseCost = (string) (DB::table('products')->where('id', $order->product_id)->value('base_cost_price') ?? 0);
                $operationsCost = (string) DB::table('production_order_operations')
                    ->where('production_order_id', $order->id)
                    ->sum('unit_cost');
                $unitCost = bcadd($baseCost, $operationsCost, 2);
                $totalCost = bcmul($unitCost, (string) $order->planned_quantity, 2);
                $completed = $order->status === 'completed';

                DB::table('production_orders')->where('id', $order->id)->update([
                    'base_unit_cost' => $baseCost,
                    'operations_unit_cost' => $operationsCost,
                    'estimated_unit_cost' => $unitCost,
                    'estimated_total_cost' => $totalCost,
                    'final_unit_cost' => $completed ? $unitCost : null,
                    'final_total_cost' => $completed ? $totalCost : null,
                ]);
                DB::table('production_order_operations')->where('production_order_id', $order->id)->update([
                    'total_cost' => DB::raw('unit_cost * '.(float) $order->planned_quantity),
                ]);
                DB::table('production_outputs')->where('production_order_id', $order->id)->update([
                    'unit_cost' => $unitCost,
                    'total_cost' => DB::raw('quantity * '.(float) $unitCost),
                ]);
            }
        });
    }
};
