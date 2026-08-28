<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Color;
use App\Models\Customer;
use App\Models\ManufacturingOperation;
use App\Models\Material;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoMasterDataSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->firstOrCreate(['email' => 'demo.dataset@abaya.test'], ['name' => 'Demo Dataset User', 'password' => Hash::make('password'), 'email_verified_at' => now()]);

        foreach ([
            ['DEMO-CUS-001', 'بوتيك نور القاهرة', '01055000001', 'القاهرة', 200000],
            ['DEMO-CUS-002', 'دار لمسة للإزياء', '01055000002', 'الإسكندرية', 150000],
            ['DEMO-CUS-003', 'معرض السلطانة', '01055000003', 'المنصورة', 120000],
            ['DEMO-CUS-004', 'بوتيك رُقي', '01055000004', 'طنطا', 100000],
            ['DEMO-CUS-005', 'عباية ستايل', '01055000005', 'الزقازيق', 80000],
        ] as [$code, $name, $phone, $city, $limit]) {
            Customer::query()->firstOrCreate(['code' => $code], ['name' => $name, 'phone' => $phone, 'city' => $city, 'credit_limit' => $limit, 'status' => 'active', 'notes' => 'DEMO-DATASET']);
        }

        foreach ([
            ['DEMO-SUP-001', 'مصانع النسيج العربية', '01056000001'],
            ['DEMO-SUP-002', 'شركة ندا للأقمشة', '01056000002'],
            ['DEMO-SUP-003', 'المتحدة لمستلزمات الخياطة', '01056000003'],
            ['DEMO-SUP-004', 'رويال للبطانات', '01056000004'],
            ['DEMO-SUP-005', 'باك لاين للتغليف', '01056000005'],
        ] as [$code, $name, $phone]) {
            Supplier::query()->firstOrCreate(['code' => $code], ['name' => $name, 'phone' => $phone, 'email' => strtolower($code).'@example.test', 'address' => 'المنطقة الصناعية، القاهرة', 'tax_number' => 'TAX-'.$code, 'status' => 'active', 'notes' => 'DEMO-DATASET']);
        }

        foreach ([
            ['DEMO-MAT-NIDA-BLK', 'قماش ندا أسود', 'fabric', 'meter', 100],
            ['DEMO-MAT-NIDA-BGE', 'قماش ندا بيج', 'fabric', 'meter', 80],
            ['DEMO-MAT-CREPE-NVY', 'قماش كريب كحلي', 'fabric', 'meter', 80],
            ['DEMO-MAT-THREAD-BLK', 'خيط بوليستر أسود', 'thread', 'roll', 20],
            ['DEMO-MAT-ZIP-60', 'سوستة مخفية 60 سم', 'zipper', 'piece', 50],
            ['DEMO-MAT-BUTTON', 'زرار كبس معدني', 'button', 'piece', 200],
            ['DEMO-MAT-LINING', 'بطانة حرير صناعي', 'lining', 'meter', 80],
            ['DEMO-MAT-PACK', 'كيس تغليف مطبوع', 'packaging', 'piece', 100],
            ['DEMO-FIFO32-NIDA-BLK', 'قماش ندا أسود', 'fabric', 'meter', 50],
            ['DEMO-FIFO150-NIDA-BLK', 'قماش ندا أسود - اختبار عبور FIFO', 'fabric', 'meter', 50],
        ] as [$code, $name, $category, $unit, $minimum]) {
            Material::query()->firstOrCreate(['code' => $code], ['name' => $name, 'category' => $category, 'unit' => $unit, 'minimum_stock' => $minimum, 'quantity' => 0, 'description' => 'خامة بيانات العرض الواقعية', 'status' => 'active']);
        }

        $categories = collect([
            ['DEMO-CAT-DAILY', 'عبايات يومية'],
            ['DEMO-CAT-OCCASION', 'عبايات مناسبات'],
        ])->mapWithKeys(fn ($row) => [$row[0] => Category::query()->firstOrCreate(['code' => $row[0]], ['name' => $row[1], 'status' => 'active'])]);
        $colors = collect([
            ['DEMO-BLK', 'أسود', '#111111'], ['DEMO-BGE', 'بيج', '#D8C3A5'], ['DEMO-NVY', 'كحلي', '#172554'], ['DEMO-OLV', 'زيتي', '#556B2F'],
        ])->mapWithKeys(fn ($row) => [$row[0] => Color::query()->firstOrCreate(['name' => $row[1]], ['code' => $row[0], 'hex_code' => $row[2], 'status' => 'active'])]);
        $sizes = collect([['M', 20], ['L', 30], ['XL', 40]])->mapWithKeys(fn ($row) => [$row[0] => Size::query()->firstOrCreate(['name' => $row[0]], ['sort_order' => $row[1], 'status' => 'active'])]);

        $products = collect([
            ['DEMO-3037', 'موديل 3037 كلاسيك', 'DEMO-CAT-DAILY', 420],
            ['DEMO-4102', 'موديل 4102 مطرز', 'DEMO-CAT-OCCASION', 520],
            ['DEMO-5201', 'موديل 5201 فراشة', 'DEMO-CAT-DAILY', 460],
            ['DEMO-6105', 'موديل 6105 سهرة', 'DEMO-CAT-OCCASION', 600],
        ])->mapWithKeys(fn ($row) => [$row[0] => Product::query()->firstOrCreate(['code' => $row[0]], ['name' => $row[1], 'category_id' => $categories[$row[2]]->id, 'base_cost_price' => $row[3], 'cost_price' => $row[3], 'status' => 'active', 'description' => 'موديل DEMO-DATASET'])]);

        $definitions = [
            ['DEMO-3037', 'DEMO-BLK', 'M'], ['DEMO-3037', 'DEMO-BLK', 'L'], ['DEMO-3037', 'DEMO-BLK', 'XL'], ['DEMO-3037', 'DEMO-BGE', 'M'], ['DEMO-3037', 'DEMO-BGE', 'L'], ['DEMO-3037', 'DEMO-BGE', 'XL'],
            ['DEMO-4102', 'DEMO-BLK', 'M'], ['DEMO-4102', 'DEMO-BLK', 'L'], ['DEMO-4102', 'DEMO-NVY', 'L'], ['DEMO-4102', 'DEMO-NVY', 'XL'],
            ['DEMO-5201', 'DEMO-BLK', 'M'], ['DEMO-5201', 'DEMO-BGE', 'L'], ['DEMO-5201', 'DEMO-OLV', 'M'], ['DEMO-5201', 'DEMO-OLV', 'XL'],
            ['DEMO-6105', 'DEMO-BLK', 'L'], ['DEMO-6105', 'DEMO-NVY', 'M'], ['DEMO-6105', 'DEMO-NVY', 'XL'], ['DEMO-6105', 'DEMO-BGE', 'L'],
        ];
        foreach ($definitions as $index => [$productCode, $colorCode, $sizeName]) {
            $sku = $productCode.'-'.$colorCode.'-'.$sizeName;
            ProductVariant::query()->firstOrCreate(['sku' => $sku], ['product_id' => $products[$productCode]->id, 'color_id' => $colors[$colorCode]->id, 'size_id' => $sizes[$sizeName]->id, 'barcode' => '629900'.str_pad((string) ($index + 1), 7, '0', STR_PAD_LEFT), 'default_price' => $productCode === 'DEMO-3037' ? 500 : ($productCode === 'DEMO-4102' ? 750 : ($productCode === 'DEMO-5201' ? 650 : 900)), 'quantity' => 0, 'status' => 'active']);
        }

        foreach ([['القص', 12], ['الخياطة', 35], ['التشطيب', 10], ['فحص الجودة', 7]] as [$name, $cost]) {
            ManufacturingOperation::query()->firstOrCreate(['name' => $name], ['description' => 'عملية تصنيع قياسية لبيانات العرض', 'default_unit_cost' => $cost, 'status' => 'active']);
        }
        foreach ([['DEMO-WRK-001', 'سلمى حسن', 'قص'], ['DEMO-WRK-002', 'نهى محمود', 'قص'], ['DEMO-WRK-003', 'منى علي', 'خياطة'], ['DEMO-WRK-004', 'أميرة محمد', 'خياطة'], ['DEMO-WRK-005', 'دعاء إبراهيم', 'تشطيب'], ['DEMO-WRK-006', 'سارة أحمد', 'تشطيب'], ['DEMO-WRK-007', 'هدى كمال', 'فحص جودة'], ['DEMO-WRK-008', 'مريم خالد', 'فحص جودة']] as [$code, $name, $specialization]) {
            Worker::query()->firstOrCreate(['code' => $code], ['name' => $name, 'phone' => '01057'.substr($code, -3), 'specialization' => $specialization, 'status' => 'active', 'notes' => 'DEMO-DATASET']);
        }
    }
}
