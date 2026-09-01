<?php

namespace App\Services\Accounting;

use App\Models\FiscalYear;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class MonthlyFiscalPeriodService
{
    public function ensureForDate(string $date): FiscalYear
    {
        $year = FiscalYear::query()
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();

        if (! $year) {
            throw ValidationException::withMessages([
                'cutover_date' => 'يجب إنشاء سنة مالية تحتوي تاريخ الـCutover أولًا.',
            ]);
        }

        $this->ensure($year);

        return $year->fresh('periods');
    }

    public function ensure(FiscalYear $year): Collection
    {
        $cursor = CarbonImmutable::parse($year->start_date)->startOfDay();
        $end = CarbonImmutable::parse($year->end_date)->endOfDay();
        $periods = collect();

        while ($cursor->lte($end)) {
            $periodEnd = $cursor->endOfMonth()->min($end);
            $name = $cursor->format('Y-m');
            $periods->push($year->periods()->firstOrCreate(
                ['name' => $name],
                ['start_date' => $cursor->toDateString(), 'end_date' => $periodEnd->toDateString(), 'status' => 'open'],
            ));
            $cursor = $periodEnd->addDay()->startOfDay();
        }

        return $periods;
    }

    public function countExpected(FiscalYear $year): int
    {
        $cursor = CarbonImmutable::parse($year->start_date)->startOfDay();
        $end = CarbonImmutable::parse($year->end_date)->endOfDay();
        $count = 0;
        while ($cursor->lte($end)) {
            $count++;
            $cursor = $cursor->endOfMonth()->min($end)->addDay()->startOfDay();
        }

        return $count;
    }

    public function countPresent(FiscalYear $year): int
    {
        return $year->periods()->whereRaw("name REGEXP '^[0-9]{4}-[0-9]{2}$'")->count();
    }
}
