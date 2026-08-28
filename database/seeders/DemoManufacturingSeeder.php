<?php

namespace Database\Seeders;

use App\Actions\ProductionMaterial\IssueProductionMaterialsAction;
use App\Actions\ProductionMaterial\RecordMaterialUsageAction;
use App\Actions\ProductionOrder\CompleteProductionOrderAction;
use App\Actions\ProductionOrder\ConfirmProductionOrderAction;
use App\Actions\ProductionOrder\CreateProductionOrderAction;
use App\Actions\ProductionOrder\RecordProductionOutputAction;
use App\Actions\WorkAssignment\CompleteWorkAssignmentAction;
use App\Actions\WorkAssignment\StartWorkAssignmentAction;
use App\Models\Bom;
use App\Models\ManufacturingOperation;
use App\Models\Material;
use App\Models\ProductionOrder;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class DemoManufacturingSeeder extends Seeder
{
    public function run(): void
    {
        Auth::login(User::query()->where('email', 'demo.dataset@abaya.test')->firstOrFail());

        $definitions = [
            ['001', 'DEMO-3037-DEMO-BLK-M', 'DEMO-MAT-NIDA-BLK', 150, 1],
            ['002', 'DEMO-3037-DEMO-BLK-L', 'DEMO-MAT-NIDA-BLK', 40, 1],
            ['003', 'DEMO-3037-DEMO-BGE-M', 'DEMO-MAT-NIDA-BGE', 40, 1.5],
            ['004', 'DEMO-4102-DEMO-BLK-M', 'DEMO-MAT-NIDA-BLK', 40, 1],
            ['005', 'DEMO-5201-DEMO-OLV-M', 'DEMO-MAT-CREPE-NVY', 40, 1.5],
            ['006', 'DEMO-6105-DEMO-NVY-M', 'DEMO-MAT-CREPE-NVY', 40, 1.5],
        ];
        $operationNames = ['القص', 'الخياطة', 'التشطيب', 'فحص الجودة'];
        $workerCodes = ['DEMO-WRK-001', 'DEMO-WRK-003', 'DEMO-WRK-005', 'DEMO-WRK-007'];

        foreach ($definitions as [$suffix, $sku, $materialCode, $planned, $materialPerUnit]) {
            $variant = ProductVariant::query()->with('product')->where('sku', $sku)->firstOrFail();
            $material = Material::query()->where('code', $materialCode)->firstOrFail();
            $bom = Bom::query()->firstOrCreate(
                ['bom_number' => 'DEMO-BOM-'.$suffix, 'version' => 1],
                ['product_id' => $variant->product_id, 'product_variant_id' => $variant->id, 'basis_quantity' => 1, 'status' => 'active', 'notes' => 'DEMO-DATASET', 'created_by' => auth()->id(), 'activated_by' => auth()->id(), 'activated_at' => '2026-01-20 09:00:00'],
            );
            $bomItem = $bom->items()->firstOrCreate(['material_id' => $material->id], ['material_code' => $material->code, 'material_name' => $material->name, 'unit' => $material->unit->value, 'quantity' => $materialPerUnit]);
            $bomOperations = collect();
            foreach ($operationNames as $index => $name) {
                $operation = ManufacturingOperation::query()->where('name', $name)->firstOrFail();
                $bomOperations[$name] = $bom->operations()->firstOrCreate(['manufacturing_operation_id' => $operation->id], ['operation_name' => $operation->name, 'unit_cost' => $operation->default_unit_cost, 'sequence' => $index + 1, 'notes' => 'DEMO-DATASET']);
            }
            $bomItem->operationAllocations()->firstOrCreate(['bom_operation_id' => $bomOperations['القص']->id], ['quantity' => $materialPerUnit]);

            $marker = 'DEMO-DATASET-PRODUCTION-'.$suffix;
            if (ProductionOrder::query()->where('notes', $marker)->exists()) {
                continue;
            }

            $assignments = [];
            foreach ($operationNames as $index => $name) {
                $operation = $bomOperations[$name];
                $assignment = ['bom_operation_id' => $operation->id, 'worker_id' => Worker::query()->where('code', $workerCodes[$index])->value('id'), 'assigned_quantity' => $planned, 'notes' => 'DEMO-DATASET'];
                if ($name === 'القص') {
                    $assignment['materials'] = [$bomItem->id => ['allocated_quantity' => $planned * $materialPerUnit]];
                }
                $assignments[] = $assignment;
            }

            $order = app(CreateProductionOrderAction::class)->handle(['product_id' => $variant->product_id, 'product_variant_id' => $variant->id, 'bom_id' => $bom->id, 'planned_quantity' => $planned, 'planned_start_date' => '2026-02-'.str_pad((string) (1 + (int) $suffix), 2, '0', STR_PAD_LEFT), 'planned_completion_date' => '2026-02-20', 'notes' => $marker, 'assignments' => $assignments]);
            app(ConfirmProductionOrderAction::class)->handle($order);

            foreach ($order->workAssignments()->with('materials')->get() as $assignment) {
                foreach ($assignment->materials as $allocation) {
                    $event = ['quantity' => $allocation->allocated_quantity, 'occurred_at' => '2026-02-10 09:00:00', 'notes' => 'DEMO-DATASET'];
                    app(IssueProductionMaterialsAction::class)->handle($allocation, $event);
                    app(RecordMaterialUsageAction::class)->handle($allocation, $event);
                }
                app(StartWorkAssignmentAction::class)->handle($assignment);
                app(CompleteWorkAssignmentAction::class)->handle($assignment, ['produced_quantity' => $planned, 'good_quantity' => $planned, 'defective_quantity' => 0, 'notes' => 'DEMO-DATASET']);
            }

            app(RecordProductionOutputAction::class)->handle($order, ['quantity' => $planned, 'output_date' => '2026-02-20', 'notes' => 'DEMO-DATASET']);
            app(CompleteProductionOrderAction::class)->handle($order);
        }

        $this->seedFifoScenario('FIFO32', 'DEMO-3037-DEMO-BLK-XL', 'DEMO-FIFO32-NIDA-BLK', 10, 3.2, '2026-02-24');
        $this->seedFifoScenario('FIFO150', 'DEMO-3037-DEMO-BGE-L', 'DEMO-FIFO150-NIDA-BLK', 150, 1, '2026-02-25');
    }

    private function seedFifoScenario(string $key, string $sku, string $materialCode, int $planned, float $materialPerUnit, string $date): void
    {
        $marker = 'DEMO-DATASET-PRODUCTION-'.$key;
        if (ProductionOrder::query()->where('notes', $marker)->exists()) {
            return;
        }

        $variant = ProductVariant::query()->with('product')->where('sku', $sku)->firstOrFail();
        $material = Material::query()->where('code', $materialCode)->firstOrFail();
        $operation = ManufacturingOperation::query()->where('name', 'القص')->firstOrFail();
        $worker = Worker::query()->where('code', 'DEMO-WRK-001')->firstOrFail();
        $bom = Bom::query()->firstOrCreate(
            ['bom_number' => 'DEMO-BOM-'.$key, 'version' => 1],
            ['product_id' => $variant->product_id, 'product_variant_id' => $variant->id, 'basis_quantity' => 1, 'status' => 'active', 'notes' => 'DEMO-DATASET-VALUED-FIFO', 'created_by' => auth()->id(), 'activated_by' => auth()->id(), 'activated_at' => $date.' 08:00:00'],
        );
        $bomItem = $bom->items()->firstOrCreate(['material_id' => $material->id], ['material_code' => $material->code, 'material_name' => $material->name, 'unit' => $material->unit->value, 'quantity' => $materialPerUnit]);
        $bomOperation = $bom->operations()->firstOrCreate(['manufacturing_operation_id' => $operation->id], ['operation_name' => $operation->name, 'unit_cost' => $operation->default_unit_cost, 'sequence' => 1, 'notes' => 'عملية واحدة لتطابق المخطط مع المسند']);
        $bomItem->operationAllocations()->firstOrCreate(['bom_operation_id' => $bomOperation->id], ['quantity' => $materialPerUnit]);
        $required = $planned * $materialPerUnit;

        $order = app(CreateProductionOrderAction::class)->handle([
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'bom_id' => $bom->id,
            'planned_quantity' => $planned,
            'planned_start_date' => $date,
            'planned_completion_date' => $date,
            'notes' => $marker,
            'assignments' => [[
                'bom_operation_id' => $bomOperation->id,
                'worker_id' => $worker->id,
                'assigned_quantity' => $planned,
                'notes' => 'تكليف FIFO متسق مع الكمية المخططة',
                'materials' => [$bomItem->id => ['allocated_quantity' => $required]],
            ]],
        ]);
        app(ConfirmProductionOrderAction::class)->handle($order);
        $assignment = $order->workAssignments()->with('materials')->firstOrFail();
        $allocation = $assignment->materials->firstOrFail();
        $event = ['quantity' => $required, 'occurred_at' => $date.' 09:00:00', 'notes' => 'صرف واستخدام خامة FIFO مُقيّمة'];
        app(IssueProductionMaterialsAction::class)->handle($allocation, $event);
        app(StartWorkAssignmentAction::class)->handle($assignment);
        app(RecordMaterialUsageAction::class)->handle($allocation, $event);
        app(CompleteWorkAssignmentAction::class)->handle($assignment, ['produced_quantity' => $planned, 'good_quantity' => $planned, 'defective_quantity' => 0, 'notes' => 'إنتاج جيد بالكامل']);
        app(RecordProductionOutputAction::class)->handle($order, ['quantity' => $planned, 'output_date' => $date, 'notes' => 'إنتاج FIFO مرحل بالكامل']);
        app(CompleteProductionOrderAction::class)->handle($order);
    }
}
