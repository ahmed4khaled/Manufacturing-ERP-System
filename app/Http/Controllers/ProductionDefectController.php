<?php

namespace App\Http\Controllers;

use App\Actions\ProductionDefect\ReviewDefectAction;
use App\Actions\ProductionRework\CompleteReworkAction;
use App\Actions\ProductionRework\CreateReworkAction;
use App\Actions\ProductionRework\StartReworkAction;
use App\Http\Requests\ProductionDefect\CompleteProductionReworkRequest;
use App\Http\Requests\ProductionDefect\StoreProductionReworkRequest;
use App\Models\ProductionDefect;
use App\Models\ProductionRework;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class ProductionDefectController extends Controller
{
    public function approve(ProductionDefect $productionDefect, ReviewDefectAction $action): RedirectResponse
    {
        return $this->run(fn () => $action->handle($productionDefect, true), 'تم اعتماد العيب.');
    }

    public function reject(ProductionDefect $productionDefect, ReviewDefectAction $action): RedirectResponse
    {
        return $this->run(fn () => $action->handle($productionDefect, false), 'تم رفض العيب.');
    }

    public function rework(StoreProductionReworkRequest $request, ProductionDefect $productionDefect, CreateReworkAction $action): RedirectResponse
    {
        return $this->run(fn () => $action->handle($productionDefect, $request->validated()), 'تم إنشاء إعادة التشغيل.');
    }

    public function startRework(ProductionRework $productionRework, StartReworkAction $action): RedirectResponse
    {
        return $this->run(fn () => $action->handle($productionRework), 'تم بدء إعادة التشغيل.');
    }

    public function completeRework(CompleteProductionReworkRequest $request, ProductionRework $productionRework, CompleteReworkAction $action): RedirectResponse
    {
        return $this->run(fn () => $action->handle($productionRework, $request->validated()), 'تم إكمال إعادة التشغيل.');
    }

    private function run(callable $callback, string $message): RedirectResponse
    {
        try {
            $callback();
        } catch (ValidationException $e) {
            return back()->with('error', $e->validator->errors()->first());
        }

        return back()->with('success', $message);
    }
}
