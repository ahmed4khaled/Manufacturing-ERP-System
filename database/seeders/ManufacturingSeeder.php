<?php

namespace Database\Seeders;

use App\Models\Bom;
use App\Models\ManufacturingOperation;
use App\Models\Material;
use App\Models\MaterialStockMovement;
use App\Models\ProductionDefect;
use App\Models\ProductionMaterialTransaction;
use App\Models\ProductionOrder;
use App\Models\ProductionOutput;
use App\Models\ProductionRework;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\WorkAssignment;
use App\Models\WorkAssignmentMaterial;
use App\Models\Worker;
use Illuminate\Database\Seeder;

class ManufacturingSeeder extends Seeder
{
    public function run(): void
    {
        $userId = User::query()->where('email', 'production@abaya.test')->value('id');
        $variant = ProductVariant::query()->with(['product', 'color', 'size'])->where('sku', 'ABY-001-BLK-M')->firstOrFail();

        $workers = collect([
            ['code' => 'WRK-001', 'name' => 'أميرة حسن', 'phone' => '01040000001', 'specialization' => 'قص'],
            ['code' => 'WRK-002', 'name' => 'منى علي', 'phone' => '01040000002', 'specialization' => 'خياطة'],
            ['code' => 'WRK-003', 'name' => 'سارة محمد', 'phone' => '01040000003', 'specialization' => 'تشطيب'],
            ['code' => 'WRK-004', 'name' => 'هدى إبراهيم', 'phone' => '01040000004', 'specialization' => 'فحص جودة'],
        ])->mapWithKeys(fn (array $data) => [
            $data['code'] => Worker::query()->updateOrCreate(['code' => $data['code']], $data + ['status' => 'active', 'notes' => 'عامل تجريبي']),
        ]);

        $operations = collect([
            ['name' => 'القص', 'description' => 'فرد القماش والقص حسب الباترون'],
            ['name' => 'الخياطة', 'description' => 'تجميع وخياطة أجزاء العباية'],
            ['name' => 'التشطيب', 'description' => 'الكي والتنظيف وتركيب الإكسسوارات'],
            ['name' => 'فحص الجودة', 'description' => 'مراجعة المقاس وجودة المنتج النهائي'],
        ])->mapWithKeys(fn (array $data) => [
            $data['name'] => ManufacturingOperation::query()->updateOrCreate(['name' => $data['name']], $data + ['status' => 'active']),
        ]);
        foreach ([10, 35, 8, 5] as $index => $unitCost) {
            $operations->values()->get($index)->update(['default_unit_cost' => $unitCost]);
        }

        $bom = Bom::query()->updateOrCreate(
            ['bom_number' => 'BOM-ABY-001', 'version' => 1],
            ['product_id' => $variant->product_id, 'product_variant_id' => $variant->id, 'basis_quantity' => 1, 'status' => 'active', 'notes' => 'قائمة مكونات قياسية تجريبية', 'created_by' => $userId, 'activated_by' => $userId, 'activated_at' => now()->subDays(12)],
        );

        $materialQuantities = [
            'MAT-FAB-001' => 3.2,
            'MAT-THR-001' => 0.08,
            'MAT-ZIP-001' => 1,
            'MAT-BTN-001' => 4,
            'MAT-PKG-001' => 1,
        ];
        $bomItems = collect();
        foreach ($materialQuantities as $code => $quantity) {
            $material = Material::query()->where('code', $code)->firstOrFail();
            $bomItems[$code] = $bom->items()->updateOrCreate(
                ['material_id' => $material->id],
                ['material_code' => $material->code, 'material_name' => $material->name, 'unit' => $material->unit->value, 'quantity' => $quantity],
            );
        }

        $bomOperations = collect();
        foreach (['القص', 'الخياطة', 'التشطيب', 'فحص الجودة'] as $index => $name) {
            $operation = $operations[$name];
            $bomOperations[$name] = $bom->operations()->updateOrCreate(
                ['manufacturing_operation_id' => $operation->id],
                ['operation_name' => $name, 'unit_cost' => $operation->default_unit_cost, 'sequence' => $index + 1, 'notes' => 'خطوة تشغيل قياسية'],
            );
        }

        $bomOperationByMaterial = [
            'MAT-FAB-001' => $bomOperations->values()->get(0),
            'MAT-THR-001' => $bomOperations->values()->get(1),
            'MAT-ZIP-001' => $bomOperations->values()->get(1),
            'MAT-BTN-001' => $bomOperations->values()->get(1),
            'MAT-PKG-001' => $bomOperations->values()->get(2),
        ];
        foreach ($bomItems as $code => $bomItem) {
            $bomItem->operationAllocations()->updateOrCreate(
                ['bom_operation_id' => $bomOperationByMaterial[$code]->id],
                ['quantity' => $bomItem->quantity],
            );
        }

        $baseUnitCost = (string) $variant->product->base_cost_price;
        $operationsUnitCost = $bomOperations->reduce(fn ($total, $operation) => bcadd($total, (string) $operation->unit_cost, 2), '0.00');
        $estimatedUnitCost = bcadd($baseUnitCost, $operationsUnitCost, 2);
        $estimatedTotalCost = bcmul($estimatedUnitCost, '20', 2);
        $variant->product->update(['cost_price' => $estimatedUnitCost]);

        $order = ProductionOrder::query()->updateOrCreate(
            ['order_number' => 'PROD-DEMO-001'],
            ['product_id' => $variant->product_id, 'product_variant_id' => $variant->id, 'bom_id' => $bom->id, 'product_name' => $variant->product->name, 'color_name' => $variant->color->name, 'size_name' => $variant->size->name, 'sku' => $variant->sku, 'bom_number' => $bom->bom_number, 'bom_version' => $bom->version, 'base_unit_cost' => $baseUnitCost, 'operations_unit_cost' => $operationsUnitCost, 'estimated_unit_cost' => $estimatedUnitCost, 'estimated_total_cost' => $estimatedTotalCost, 'final_unit_cost' => $estimatedUnitCost, 'final_total_cost' => $estimatedTotalCost, 'planned_quantity' => 20, 'planned_start_date' => now()->subDays(6), 'planned_completion_date' => now()->subDay(), 'status' => 'completed', 'notes' => 'أمر إنتاج مكتمل لعرض دورة التشغيل', 'created_by' => $userId, 'confirmed_by' => $userId, 'closed_by' => $userId, 'confirmed_at' => now()->subDays(7), 'closed_at' => now()->subDay()],
        );

        $orderMaterials = collect();
        foreach ($bomItems as $code => $bomItem) {
            $required = (float) $bomItem->quantity * 20;
            $orderMaterials[$code] = $order->materials()->updateOrCreate(
                ['material_id' => $bomItem->material_id],
                ['bom_item_id' => $bomItem->id, 'material_code' => $bomItem->material_code, 'material_name' => $bomItem->material_name, 'unit' => $bomItem->unit, 'standard_quantity' => $bomItem->quantity, 'required_quantity' => $required, 'is_overridden' => false],
            );
        }

        $orderOperations = collect();
        foreach ($bomOperations as $name => $bomOperation) {
            $orderOperations[$name] = $order->operations()->updateOrCreate(
                ['manufacturing_operation_id' => $bomOperation->manufacturing_operation_id],
                ['bom_operation_id' => $bomOperation->id, 'operation_name' => $name, 'unit_cost' => $bomOperation->unit_cost, 'total_cost' => bcmul((string) $bomOperation->unit_cost, '20', 2), 'sequence' => $bomOperation->sequence, 'notes' => 'عملية منسوخة من قائمة المكونات'],
            );
        }

        $workerByOperation = ['القص' => 'WRK-001', 'الخياطة' => 'WRK-002', 'التشطيب' => 'WRK-003', 'فحص الجودة' => 'WRK-004'];
        $assignments = collect();
        foreach ($workerByOperation as $operationName => $workerCode) {
            $operation = $orderOperations[$operationName];
            $assignments[$operationName] = WorkAssignment::query()->updateOrCreate(
                ['production_order_operation_id' => $operation->id, 'worker_id' => $workers[$workerCode]->id],
                ['production_order_id' => $order->id, 'assigned_quantity' => 20, 'produced_quantity' => 20, 'good_quantity' => $operationName === 'الخياطة' ? 19 : 20, 'defective_quantity' => $operationName === 'الخياطة' ? 1 : 0, 'status' => 'completed', 'started_at' => now()->subDays(5), 'completed_at' => now()->subDays(2), 'notes' => 'تكليف تشغيل تجريبي', 'assigned_by' => $userId, 'completed_by' => $userId],
            );
        }

        $cuttingAssignment = $assignments['القص'];
        foreach ($orderMaterials as $code => $orderMaterial) {
            $cuttingAssignment = $assignments[$bomOperationByMaterial[$code]->operation_name];
            $assignmentMaterial = WorkAssignmentMaterial::query()->updateOrCreate(
                ['work_assignment_id' => $cuttingAssignment->id, 'production_order_material_id' => $orderMaterial->id],
                ['standard_quantity' => $orderMaterial->required_quantity, 'allocated_quantity' => $orderMaterial->required_quantity, 'is_overridden' => false, 'allocated_by' => $userId],
            );
            ProductionMaterialTransaction::query()->updateOrCreate(
                ['work_assignment_material_id' => $assignmentMaterial->id, 'type' => 'usage'],
                ['production_order_id' => $order->id, 'work_assignment_id' => $cuttingAssignment->id, 'worker_id' => $cuttingAssignment->worker_id, 'material_id' => $orderMaterial->material_id, 'quantity' => $orderMaterial->required_quantity, 'status' => 'approved', 'occurred_at' => now()->subDays(4), 'notes' => 'استهلاك خامة لأمر الإنتاج', 'recorded_by' => $userId, 'reviewed_by' => $userId, 'reviewed_at' => now()->subDays(3)],
            );
        }

        foreach ($orderMaterials as $code => $orderMaterial) {
            $bomOperation = $bomOperationByMaterial[$code];
            $productionOperation = $orderOperations[$bomOperation->operation_name];
            $assignment = $assignments[$bomOperation->operation_name];
            $operationRequirement = $orderMaterial->operationRequirements()->updateOrCreate(
                ['production_order_operation_id' => $productionOperation->id],
                ['standard_quantity' => $orderMaterial->required_quantity, 'required_quantity' => $orderMaterial->required_quantity],
            );
            $assignmentMaterial = WorkAssignmentMaterial::query()
                ->where('production_order_material_id', $orderMaterial->id)
                ->firstOrFail();
            $assignmentMaterial->update([
                'work_assignment_id' => $assignment->id,
                'production_order_material_operation_id' => $operationRequirement->id,
            ]);
            ProductionMaterialTransaction::query()
                ->where('work_assignment_material_id', $assignmentMaterial->id)
                ->update(['work_assignment_id' => $assignment->id, 'worker_id' => $assignment->worker_id]);
        }

        $fabricOrderMaterial = $orderMaterials['MAT-FAB-001'];
        MaterialStockMovement::query()->updateOrCreate(
            ['reference_type' => ProductionOrder::class, 'reference_id' => $order->id, 'material_id' => $fabricOrderMaterial->material_id],
            ['movement_type' => 'production_issue', 'direction' => 'out', 'quantity' => $fabricOrderMaterial->required_quantity, 'reference_number' => $order->order_number, 'movement_date' => now()->subDays(4), 'notes' => 'صرف قماش للإنتاج', 'user_id' => $userId],
        );

        $sewingAssignment = $assignments['الخياطة'];
        $defect = ProductionDefect::query()->updateOrCreate(
            ['work_assignment_id' => $sewingAssignment->id, 'reason' => 'sewing_defect'],
            ['production_order_id' => $order->id, 'worker_id' => $sewingAssignment->worker_id, 'production_order_operation_id' => $sewingAssignment->production_order_operation_id, 'product_variant_id' => $variant->id, 'quantity' => 1, 'status' => 'rework', 'notes' => 'غرزة غير منتظمة وتم تحويلها لإعادة التشغيل', 'recorded_by' => $userId, 'reviewed_by' => $userId, 'reviewed_at' => now()->subDays(2)],
        );
        ProductionRework::query()->updateOrCreate(
            ['production_defect_id' => $defect->id],
            ['production_order_id' => $order->id, 'source_work_assignment_id' => $sewingAssignment->id, 'production_order_operation_id' => $sewingAssignment->production_order_operation_id, 'worker_id' => $sewingAssignment->worker_id, 'assigned_quantity' => 1, 'good_quantity' => 1, 'defective_quantity' => 0, 'status' => 'completed', 'notes' => 'تم إصلاح عيب الخياطة', 'created_by' => $userId, 'completed_by' => $userId, 'started_at' => now()->subDays(2), 'completed_at' => now()->subDay()],
        );

        ProductionOutput::query()->updateOrCreate(
            ['production_order_id' => $order->id, 'output_date' => now()->subDay()->toDateString()],
            ['product_variant_id' => $variant->id, 'quantity' => 20, 'unit_cost' => $estimatedUnitCost, 'total_cost' => $estimatedTotalCost, 'notes' => 'إنتاج نهائي مقبول', 'recorded_by' => $userId, 'posted_at' => now()->subDay()],
        );
        StockMovement::query()->updateOrCreate(
            ['reference_type' => ProductionOrder::class, 'reference_id' => $order->id, 'product_variant_id' => $variant->id],
            ['movement_type' => 'production_output', 'direction' => 'in', 'quantity' => 20, 'reference_number' => $order->order_number, 'movement_date' => now()->subDay(), 'notes' => 'إضافة إنتاج تام للمخزون', 'user_id' => $userId],
        );

        foreach ([
            'created' => 'تم إنشاء أمر الإنتاج',
            'confirmed' => 'تم اعتماد أمر الإنتاج',
            'completed' => 'تم إغلاق أمر الإنتاج وإضافة الناتج للمخزون',
        ] as $action => $description) {
            $order->activities()->updateOrCreate(
                ['action' => $action],
                ['description' => $description, 'metadata' => ['source' => 'demo-seeder'], 'user_id' => $userId],
            );
        }
    }
}
