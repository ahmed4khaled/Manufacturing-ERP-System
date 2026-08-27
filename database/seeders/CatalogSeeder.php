<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Color;
use App\Models\Material;
use App\Models\MaterialStockMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = User::query()->where('email', 'admin@abaya.test')->value('id');

        $categories = collect([
            ['code' => 'CAT-CLASSIC', 'name' => 'عبايات كلاسيك', 'description' => 'تصميمات يومية كلاسيكية'],
            ['code' => 'CAT-MODERN', 'name' => 'عبايات مودرن', 'description' => 'تصميمات عصرية للمناسبات'],
            ['code' => 'CAT-PRAYER', 'name' => 'عبايات صلاة', 'description' => 'عبايات مريحة للصلاة'],
        ])->mapWithKeys(fn (array $data) => [
            $data['code'] => Category::query()->updateOrCreate(['code' => $data['code']], $data + ['status' => 'active']),
        ]);

        $colors = collect([
            ['code' => 'BLK', 'name' => 'أسود', 'hex_code' => '#111111'],
            ['code' => 'NVY', 'name' => 'كحلي', 'hex_code' => '#1F2A44'],
            ['code' => 'GRN', 'name' => 'زيتي', 'hex_code' => '#596643'],
            ['code' => 'BRG', 'name' => 'نبيتي', 'hex_code' => '#722F37'],
        ])->mapWithKeys(fn (array $data) => [
            $data['code'] => Color::query()->updateOrCreate(['code' => $data['code']], $data + ['status' => 'active']),
        ]);

        $sizes = collect([
            ['name' => 'S', 'sort_order' => 1],
            ['name' => 'M', 'sort_order' => 2],
            ['name' => 'L', 'sort_order' => 3],
            ['name' => 'XL', 'sort_order' => 4],
            ['name' => 'XXL', 'sort_order' => 5],
        ])->mapWithKeys(fn (array $data) => [
            $data['name'] => Size::query()->updateOrCreate(['name' => $data['name']], $data + ['status' => 'active']),
        ]);

        $products = collect([
            ['code' => 'ABY-001', 'category' => 'CAT-CLASSIC', 'name' => 'عباية سادة يومية', 'cost_price' => 650, 'description' => 'عباية عملية بقصة مستقيمة'],
            ['code' => 'ABY-002', 'category' => 'CAT-MODERN', 'name' => 'عباية تطريز ناعم', 'cost_price' => 920, 'description' => 'عباية بتطريز يدوي بسيط'],
            ['code' => 'ABY-003', 'category' => 'CAT-PRAYER', 'name' => 'عباية صلاة واسعة', 'cost_price' => 480, 'description' => 'قماش خفيف وقصة مريحة'],
        ])->mapWithKeys(function (array $data) use ($categories) {
            $category = $data['category'];
            unset($data['category']);
            $data['base_cost_price'] = $data['cost_price'];

            return [$data['code'] => Product::query()->updateOrCreate(
                ['code' => $data['code']],
                $data + ['category_id' => $categories[$category]->id, 'status' => 'active'],
            )];
        });

        $variantDefinitions = [
            ['product' => 'ABY-001', 'color' => 'BLK', 'size' => 'M', 'sku' => 'ABY-001-BLK-M', 'barcode' => '6221000000011', 'price' => 950, 'quantity' => 40],
            ['product' => 'ABY-001', 'color' => 'BLK', 'size' => 'L', 'sku' => 'ABY-001-BLK-L', 'barcode' => '6221000000028', 'price' => 950, 'quantity' => 35],
            ['product' => 'ABY-001', 'color' => 'NVY', 'size' => 'XL', 'sku' => 'ABY-001-NVY-XL', 'barcode' => '6221000000035', 'price' => 990, 'quantity' => 22],
            ['product' => 'ABY-002', 'color' => 'BLK', 'size' => 'L', 'sku' => 'ABY-002-BLK-L', 'barcode' => '6221000000042', 'price' => 1450, 'quantity' => 18],
            ['product' => 'ABY-002', 'color' => 'BRG', 'size' => 'XL', 'sku' => 'ABY-002-BRG-XL', 'barcode' => '6221000000059', 'price' => 1550, 'quantity' => 14],
            ['product' => 'ABY-003', 'color' => 'GRN', 'size' => 'XXL', 'sku' => 'ABY-003-GRN-XXL', 'barcode' => '6221000000066', 'price' => 750, 'quantity' => 28],
        ];

        foreach ($variantDefinitions as $data) {
            $variant = ProductVariant::query()->updateOrCreate(
                ['sku' => $data['sku']],
                [
                    'product_id' => $products[$data['product']]->id,
                    'color_id' => $colors[$data['color']]->id,
                    'size_id' => $sizes[$data['size']]->id,
                    'barcode' => $data['barcode'],
                    'default_price' => $data['price'],
                    'quantity' => $data['quantity'],
                    'status' => 'active',
                ],
            );

            StockMovement::query()->updateOrCreate(
                ['reference_type' => 'seeder', 'reference_number' => 'OPEN-'.$data['sku']],
                ['product_variant_id' => $variant->id, 'movement_type' => 'opening', 'direction' => 'in', 'quantity' => $data['quantity'], 'movement_date' => now()->subDays(30)->toDateString(), 'notes' => 'رصيد افتتاحي تجريبي', 'user_id' => $adminId],
            );
        }

        $materials = [
            ['code' => 'MAT-FAB-001', 'name' => 'قماش ندا أسود', 'category' => 'fabric', 'unit' => 'meter', 'minimum_stock' => 100, 'quantity' => 850],
            ['code' => 'MAT-FAB-002', 'name' => 'قماش كريب كحلي', 'category' => 'fabric', 'unit' => 'meter', 'minimum_stock' => 80, 'quantity' => 620],
            ['code' => 'MAT-THR-001', 'name' => 'خيط بوليستر أسود', 'category' => 'thread', 'unit' => 'roll', 'minimum_stock' => 20, 'quantity' => 140],
            ['code' => 'MAT-ZIP-001', 'name' => 'سوستة مخفية 60 سم', 'category' => 'zipper', 'unit' => 'piece', 'minimum_stock' => 50, 'quantity' => 320],
            ['code' => 'MAT-BTN-001', 'name' => 'زرار كبس أسود', 'category' => 'button', 'unit' => 'piece', 'minimum_stock' => 200, 'quantity' => 1200],
            ['code' => 'MAT-PKG-001', 'name' => 'كيس تغليف مطبوع', 'category' => 'packaging', 'unit' => 'piece', 'minimum_stock' => 100, 'quantity' => 500],
        ];

        foreach ($materials as $data) {
            $material = Material::query()->updateOrCreate(['code' => $data['code']], $data + ['status' => 'active', 'description' => 'خامة تشغيل تجريبية']);
            MaterialStockMovement::query()->updateOrCreate(
                ['reference_type' => 'seeder', 'reference_number' => 'OPEN-'.$data['code']],
                ['material_id' => $material->id, 'movement_type' => 'opening', 'direction' => 'in', 'quantity' => $data['quantity'], 'movement_date' => now()->subDays(30)->toDateString(), 'notes' => 'رصيد افتتاحي تجريبي', 'user_id' => $adminId],
            );
        }
    }
}
