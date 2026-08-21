<?php

namespace App\Actions\Worker;

use App\Models\Worker;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteWorkerAction
{
    public function handle(Worker $worker): void
    {
        DB::transaction(function () use ($worker) {
            $worker = Worker::query()->lockForUpdate()->findOrFail($worker->id);
            if ($worker->workAssignments()->whereNotIn('status', ['completed', 'cancelled'])->exists()) {
                throw ValidationException::withMessages(['worker' => 'لا يمكن حذف عامل لديه تكليفات عمل مفتوحة.']);
            }
            $worker->delete();
        });
    }
}
