<?php

namespace App\Actions\Color;

use App\Models\Color;
use Illuminate\Support\Facades\DB;

class UpdateColorAction
{
    public function handle(Color $color, array $data): Color
    {
        return DB::transaction(function () use ($color, $data) {
            $color->update($data);

            return $color->fresh();
        });
    }
}
