<?php

use App\Models\Color;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('colors', 'code')) {
            Schema::table('colors', function (Blueprint $table) {
                $table->string('code', 10)->nullable()->unique()->after('id');
            });

            Color::query()->each(function (Color $color) {
                $base = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $color->name), 0, 3)) ?: 'CLR';
                $code = $base;
                $suffix = 1;

                while (Color::query()->where('code', $code)->where('id', '!=', $color->id)->exists()) {
                    $code = $base.$suffix;
                    $suffix++;
                }

                $color->update(['code' => $code]);
            });

            Schema::table('colors', function (Blueprint $table) {
                $table->string('code', 10)->nullable(false)->change();
            });
        }

        $duplicateHexCodes = DB::table('colors')
            ->select('hex_code')
            ->whereNotNull('hex_code')
            ->groupBy('hex_code')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('hex_code');

        foreach ($duplicateHexCodes as $hexCode) {
            $keepId = Color::query()->where('hex_code', $hexCode)->orderBy('id')->value('id');

            Color::query()
                ->where('hex_code', $hexCode)
                ->where('id', '!=', $keepId)
                ->update(['hex_code' => null]);
        }

        $indexExists = collect(DB::select("SHOW INDEX FROM colors WHERE Key_name = 'colors_hex_code_unique'"))->isNotEmpty();

        if (! $indexExists) {
            Schema::table('colors', function (Blueprint $table) {
                $table->unique('hex_code');
            });
        }
    }

    public function down(): void
    {
        Schema::table('colors', function (Blueprint $table) {
            $table->dropUnique(['hex_code']);
            $table->dropColumn('code');
        });
    }
};
