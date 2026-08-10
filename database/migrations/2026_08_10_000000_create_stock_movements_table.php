<?php

use App\Enums\StockMovementDirection;
use App\Enums\StockMovementType;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->string('movement_type');
            $table->string('direction');
            $table->unsignedInteger('quantity');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_number')->nullable();
            $table->date('movement_date');
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_variant_id', 'movement_date']);
            $table->index('movement_type');
            $table->index(['reference_type', 'reference_id']);
        });

        ProductVariant::query()
            ->where('quantity', '>', 0)
            ->each(function (ProductVariant $variant) {
                StockMovement::create([
                    'product_variant_id' => $variant->id,
                    'movement_type' => StockMovementType::OPENING,
                    'direction' => StockMovementDirection::IN,
                    'quantity' => $variant->quantity,
                    'movement_date' => now()->toDateString(),
                    'notes' => 'Opening stock migrated from existing variant quantity.',
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
