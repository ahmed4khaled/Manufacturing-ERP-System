<?php

namespace App\Actions\Color;

use App\Enums\ColorStatus;
use App\Models\Color;
use Illuminate\Support\Facades\DB;

class CreateColorAction
{
    public function handle(array $data): Color
    {
        return DB::transaction(function () use ($data) {
            $data['status'] = ColorStatus::ACTIVE;

            return Color::create($data);
        });
    }
}
