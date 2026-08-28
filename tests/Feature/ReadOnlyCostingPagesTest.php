<?php

use App\Models\Category;
use App\Models\Color;
use App\Models\Customer;
use App\Models\Material;
use App\Models\MaterialCostLayer;
use App\Models\MaterialStockMovement;
use App\Models\Product;
use App\Models\ProductCostAllocation;
use App\Models\ProductCostLayer;
use App\Models\ProductVariant;
use App\Models\SalesInvoice;
use App\Models\Size;
use App\Models\StockMovement;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function costingInspectionUser(array $permissions): User
{
    $user = User::factory()->create();

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user->givePermissionTo($permissions);

    return $user;
}

/** @return array{material: Material, layer: MaterialCostLayer} */
function costingInspectionMaterial(): array
{
    $material = Material::create(['code' => 'UI-MAT', 'name' => 'UI Fabric', 'category' => 'fabric', 'unit' => 'meter', 'minimum_stock' => 0, 'quantity' => 100, 'status' => 'active']);
    $movement = MaterialStockMovement::create(['material_id' => $material->id, 'movement_type' => 'purchase_receipt', 'direction' => 'in', 'quantity' => 100, 'reference_number' => 'UI-GR', 'movement_date' => '2026-08-01']);
    $layer = MaterialCostLayer::create(['material_id' => $material->id, 'material_stock_movement_id' => $movement->id, 'layer_date' => '2026-08-01', 'original_quantity' => 100, 'remaining_quantity' => 50, 'unit_cost' => 12, 'total_original_cost' => 1200]);

    return compact('material', 'layer');
}

/** @return array{variant: ProductVariant, layer: ProductCostLayer} */
function costingInspectionProduct(): array
{
    $category = Category::create(['code' => 'UI-CAT', 'name' => 'UI Category', 'status' => 'active']);
    $product = Product::create(['category_id' => $category->id, 'code' => 'UI-P', 'name' => 'UI Abaya', 'base_cost_price' => 300, 'cost_price' => 300, 'status' => 'active']);
    $color = Color::create(['code' => 'UI-BLK', 'name' => 'UI Black', 'status' => 'active']);
    $size = Size::create(['name' => 'UI Medium', 'sort_order' => 1, 'status' => 'active']);
    $variant = ProductVariant::create(['product_id' => $product->id, 'color_id' => $color->id, 'size_id' => $size->id, 'sku' => 'UI-SKU', 'barcode' => 'UI-BAR', 'default_price' => 500, 'standard_cost' => 290, 'last_production_cost' => 300, 'actual_cost' => 300, 'quantity' => 100, 'status' => 'active']);
    $movement = StockMovement::create(['product_variant_id' => $variant->id, 'movement_type' => 'production_output', 'direction' => 'in', 'quantity' => 100, 'movement_date' => '2026-08-02']);
    $layer = ProductCostLayer::create(['product_variant_id' => $variant->id, 'stock_movement_id' => $movement->id, 'layer_date' => '2026-08-02', 'original_quantity' => 100, 'remaining_quantity' => 80, 'unit_cost' => 300, 'total_original_cost' => 30000, 'is_final' => true]);

    return compact('variant', 'layer');
}

it('protects cost layer inspection routes with existing inventory permissions', function () {
    $user = User::factory()->create();
    $layer = costingInspectionMaterial()['layer'];

    $this->actingAs($user)->get(route('material-inventory.cost-layers.index'))->assertForbidden();
    $this->actingAs($user)->get(route('material-inventory.cost-layers.show', $layer))->assertForbidden();
    $this->actingAs($user)->get(route('inventory.cost-layers.index'))->assertForbidden();
});

it('shows material FIFO layers and their read-only details', function () {
    $user = costingInspectionUser(['material_inventory.view']);
    ['layer' => $layer] = costingInspectionMaterial();

    $this->actingAs($user)
        ->get(route('material-inventory.cost-layers.index'))
        ->assertOk()
        ->assertSee('طبقات تكلفة الخامات FIFO')
        ->assertSee('UI Fabric')
        ->assertSee('50.000')
        ->assertSee('12.0000');

    $this->get(route('material-inventory.cost-layers.show', $layer))
        ->assertOk()
        ->assertSee('بيانات الطبقة')
        ->assertSee('1,200.00')
        ->assertSee('تخصيصات الإنتاج التي استهلكت الطبقة');
});

it('shows finished goods layers and product variant FIFO valuation', function () {
    $user = costingInspectionUser(['inventory.view']);
    ['variant' => $variant, 'layer' => $layer] = costingInspectionProduct();

    $this->actingAs($user)
        ->get(route('inventory.cost-layers.index'))
        ->assertOk()
        ->assertSee('طبقات تكلفة المنتجات التامة FIFO')
        ->assertSee('UI-SKU')
        ->assertSee('30,000.00');

    $this->get(route('product-variants.show', $variant))
        ->assertOk()
        ->assertSee('تكلفة متغير المنتج وطبقات FIFO')
        ->assertSee('24,000.00')
        ->assertSee('80.000')
        ->assertSee('#'.$layer->id);
});

it('shows confirmed sales invoice COGS from its recorded FIFO allocations', function () {
    $user = costingInspectionUser(['sales_invoices.view']);
    ['variant' => $variant, 'layer' => $layer] = costingInspectionProduct();
    $customer = Customer::create(['code' => 'UI-CUS', 'name' => 'UI Customer', 'phone' => '01000000123', 'credit_limit' => 100000, 'status' => 'active']);
    $invoice = SalesInvoice::create(['invoice_number' => 'UI-INV', 'customer_id' => $customer->id, 'invoice_date' => '2026-08-03', 'status' => 'confirmed', 'total_quantity' => 20, 'subtotal' => 10000, 'discount' => 0, 'total_amount' => 10000, 'total_cogs' => 6000]);
    $invoice->items()->create(['product_variant_id' => $variant->id, 'product_name' => 'UI Abaya', 'color_name' => 'UI Black', 'size_name' => 'UI Medium', 'sku' => 'UI-SKU', 'quantity' => 20, 'unit_price' => 500, 'line_total' => 10000]);
    $saleMovement = StockMovement::create(['product_variant_id' => $variant->id, 'movement_type' => 'sale', 'direction' => 'out', 'quantity' => 20, 'reference_type' => SalesInvoice::class, 'reference_id' => $invoice->id, 'movement_date' => '2026-08-03']);
    ProductCostAllocation::create(['product_cost_layer_id' => $layer->id, 'stock_movement_id' => $saleMovement->id, 'reference_type' => SalesInvoice::class, 'reference_id' => $invoice->id, 'direction' => 'out', 'quantity' => 20, 'unit_cost' => 300, 'total_cost' => 6000]);

    $this->actingAs($user)
        ->get(route('sales-invoices.show', $invoice))
        ->assertOk()
        ->assertSee('تكلفة البضاعة المباعة — تخصيصات FIFO')
        ->assertSee('#'.$layer->id)
        ->assertSee('20.000')
        ->assertSee('300.0000')
        ->assertSee('6,000.00');
});

it('does not expose costing sections without their view permissions', function () {
    ['variant' => $variant] = costingInspectionProduct();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('product-variants.show', $variant))
        ->assertOk()
        ->assertDontSee('تكلفة متغير المنتج وطبقات FIFO');
});
