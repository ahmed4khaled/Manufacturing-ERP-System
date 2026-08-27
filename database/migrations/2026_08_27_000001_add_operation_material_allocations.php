<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bom_item_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bom_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bom_operation_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->timestamps();

            $table->unique(['bom_item_id', 'bom_operation_id'], 'bom_item_operation_unique');
        });

        Schema::create('production_order_material_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_material_id')
                ->constrained(indexName: 'pomo_material_fk')
                ->cascadeOnDelete();
            $table->foreignId('production_order_operation_id')
                ->constrained(indexName: 'pomo_operation_fk')
                ->cascadeOnDelete();
            $table->decimal('standard_quantity', 14, 3);
            $table->decimal('required_quantity', 14, 3);
            $table->timestamps();

            $table->unique(
                ['production_order_material_id', 'production_order_operation_id'],
                'production_material_operation_unique',
            );
        });

        Schema::table('work_assignment_materials', function (Blueprint $table) {
            $table->foreignId('production_order_material_operation_id')
                ->nullable()
                ->after('production_order_material_id')
                ->constrained('production_order_material_operations', indexName: 'wam_material_operation_fk')
                ->nullOnDelete();
        });

        $this->backfillBomMappings();
        $this->backfillProductionOrderSnapshots();
    }

    public function down(): void
    {
        Schema::table('work_assignment_materials', function (Blueprint $table) {
            $table->dropForeign('wam_material_operation_fk');
            $table->dropColumn('production_order_material_operation_id');
        });

        Schema::dropIfExists('production_order_material_operations');
        Schema::dropIfExists('bom_item_operations');
    }

    private function backfillBomMappings(): void
    {
        DB::table('bom_items')->orderBy('id')->chunkById(100, function ($items): void {
            foreach ($items as $item) {
                $operationId = DB::table('bom_operations')
                    ->where('bom_id', $item->bom_id)
                    ->orderBy('sequence')
                    ->value('id');

                if ($operationId) {
                    // Legacy BOMs behaved as if every material belonged to the first operation.
                    DB::table('bom_item_operations')->insert([
                        'bom_item_id' => $item->id,
                        'bom_operation_id' => $operationId,
                        'quantity' => $item->quantity,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });
    }

    private function backfillProductionOrderSnapshots(): void
    {
        DB::table('production_order_materials')->orderBy('id')->chunkById(100, function ($materials): void {
            foreach ($materials as $material) {
                $allocations = DB::table('work_assignment_materials as wam')
                    ->join('work_assignments as wa', 'wa.id', '=', 'wam.work_assignment_id')
                    ->where('wam.production_order_material_id', $material->id)
                    ->groupBy('wa.production_order_operation_id')
                    ->selectRaw('wa.production_order_operation_id, SUM(wam.allocated_quantity) as quantity')
                    ->get();

                if ($allocations->isEmpty()) {
                    $operationId = DB::table('production_order_operations')
                        ->where('production_order_id', $material->production_order_id)
                        ->orderBy('sequence')
                        ->value('id');

                    if ($operationId) {
                        $allocations = collect([(object) [
                            'production_order_operation_id' => $operationId,
                            'quantity' => $material->required_quantity,
                        ]]);
                    }
                }

                foreach ($allocations as $allocation) {
                    $snapshotId = DB::table('production_order_material_operations')->insertGetId([
                        'production_order_material_id' => $material->id,
                        'production_order_operation_id' => $allocation->production_order_operation_id,
                        'standard_quantity' => $allocation->quantity,
                        'required_quantity' => $allocation->quantity,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('work_assignment_materials')
                        ->where('production_order_material_id', $material->id)
                        ->whereIn('work_assignment_id', DB::table('work_assignments')
                            ->select('id')
                            ->where('production_order_operation_id', $allocation->production_order_operation_id))
                        ->update(['production_order_material_operation_id' => $snapshotId]);
                }
            }
        });
    }
};
