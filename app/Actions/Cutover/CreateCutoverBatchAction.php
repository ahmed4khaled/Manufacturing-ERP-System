<?php

namespace App\Actions\Cutover;

use App\Models\CutoverBatch;
use App\Services\Accounting\MonthlyFiscalPeriodService;
use App\Services\CodeGeneratorService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class CreateCutoverBatchAction
{
    public function __construct(
        private readonly CodeGeneratorService $codes,
        private readonly MonthlyFiscalPeriodService $periods,
    ) {}

    public function handle(array $data, ?int $createdBy): CutoverBatch
    {
        return DB::transaction(function () use ($data, $createdBy): CutoverBatch {
            $existing = CutoverBatch::query()->where('reference', $data['reference'])->first();
            if ($existing) {
                return $existing;
            }
            $this->periods->ensureForDate($data['cutover_date']);
            try {
                return CutoverBatch::query()->create([
                    'batch_number' => $this->codes->next('CUT'),
                    'reference' => $data['reference'],
                    'cutover_date' => $data['cutover_date'],
                    'description' => $data['description'] ?? null,
                    'status' => CutoverBatch::STATUS_DRAFT,
                    'created_by' => $createdBy,
                ]);
            } catch (QueryException $exception) {
                return CutoverBatch::query()->where('reference', $data['reference'])->firstOrFail();
            }
        });
    }
}
