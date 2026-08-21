<?php

namespace App\Actions\Worker;

use App\Models\Worker;
use App\Services\CodeGeneratorService;
use Illuminate\Support\Facades\DB;

class CreateWorkerAction
{
    public function __construct(private readonly CodeGeneratorService $codes) {}

    public function handle(array $data): Worker
    {
        return DB::transaction(fn () => Worker::create($data + ['code' => $this->codes->next('WRK')]));
    }
}
