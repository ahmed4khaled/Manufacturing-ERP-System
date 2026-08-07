<?php

namespace App\Actions\Size;

use App\Models\Size;
use Illuminate\Support\Facades\DB;

class UpdateSizeAction
{
    public function handle(Size $size, array $data): Size
    {
        return DB::transaction(function () use ($size, $data) {
            $size->update($data);

            return $size->fresh();
        });
    }
}
