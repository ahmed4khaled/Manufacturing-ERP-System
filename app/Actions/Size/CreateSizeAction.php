<?php

namespace App\Actions\Size;

use App\Enums\SizeStatus;
use App\Models\Size;
use Illuminate\Support\Facades\DB;

class CreateSizeAction
{
    public function handle(array $data): Size
    {
        return DB::transaction(function () use ($data) {
            $data['status'] = SizeStatus::ACTIVE;

            return Size::create($data);
        });
    }
}
