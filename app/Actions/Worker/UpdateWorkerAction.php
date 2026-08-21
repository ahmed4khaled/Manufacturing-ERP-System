<?php

namespace App\Actions\Worker;

use App\Models\Worker;
use Illuminate\Support\Facades\DB;

class UpdateWorkerAction
{
    public function handle(Worker $worker, array $data): Worker
    {
        return DB::transaction(function () use ($worker, $data) {
            $worker = Worker::query()->lockForUpdate()->findOrFail($worker->id);
            $worker->update($data);

            return $worker->fresh();
        });
    }
}
