<?php

namespace App\Http\Controllers;

use App\Actions\ProductionDefect\RecordDefectAction;
use App\Actions\ProductionMaterial\IssueProductionMaterialsAction;
use App\Actions\ProductionMaterial\RecordMaterialReturnAction;
use App\Actions\ProductionMaterial\RecordMaterialUsageAction;
use App\Actions\ProductionMaterial\RecordWasteAction;
use App\Actions\ProductionMaterial\ReviewWasteAction;
use App\Actions\WorkAssignment\CompleteWorkAssignmentAction;
use App\Actions\WorkAssignment\StartWorkAssignmentAction;
use App\Http\Requests\ProductionDefect\StoreProductionDefectRequest;
use App\Http\Requests\WorkAssignment\CompleteWorkAssignmentRequest;
use App\Http\Requests\WorkAssignment\RecordMaterialTransactionRequest;
use App\Models\ProductionMaterialTransaction;
use App\Models\WorkAssignment;
use App\Models\WorkAssignmentMaterial;
use App\Services\Printing\DocumentPrinter;
use App\Services\ProductionSummaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WorkAssignmentController extends Controller
{
    public function show(WorkAssignment $workAssignment, ProductionSummaryService $summary): View
    {
        $workAssignment->load(['productionOrder', 'operation', 'worker', 'assigner', 'completer', 'materials.productionOrderMaterial', 'materials.transactions.recorder', 'materials.transactions.reviewer', 'defects.recorder', 'defects.reviewer']);
        foreach ($workAssignment->materials as $allocation) {
            $allocation->accountability = $summary->materialTotals($allocation);
        }

        return view('work-assignments.show', compact('workAssignment'));
    }

    public function start(WorkAssignment $workAssignment, StartWorkAssignmentAction $action): RedirectResponse
    {
        return $this->run(fn () => $action->handle($workAssignment), 'تم بدء التكليف.');
    }

    public function complete(CompleteWorkAssignmentRequest $request, WorkAssignment $workAssignment, CompleteWorkAssignmentAction $action): RedirectResponse
    {
        return $this->run(fn () => $action->handle($workAssignment, $request->validated()), 'تم إكمال التكليف.');
    }

    public function defect(StoreProductionDefectRequest $request, WorkAssignment $workAssignment, RecordDefectAction $action): RedirectResponse
    {
        return $this->run(fn () => $action->handle($workAssignment, $request->validated()), 'تم تسجيل العيب للمراجعة.');
    }

    public function issue(RecordMaterialTransactionRequest $request, WorkAssignmentMaterial $allocation, IssueProductionMaterialsAction $action): RedirectResponse
    {
        return $this->run(fn () => $action->handle($allocation, $request->validated()), 'تم صرف الخامة.');
    }

    public function usage(RecordMaterialTransactionRequest $request, WorkAssignmentMaterial $allocation, RecordMaterialUsageAction $action): RedirectResponse
    {
        return $this->run(fn () => $action->handle($allocation, $request->validated()), 'تم تسجيل الاستخدام.');
    }

    public function returnMaterial(RecordMaterialTransactionRequest $request, WorkAssignmentMaterial $allocation, RecordMaterialReturnAction $action): RedirectResponse
    {
        return $this->run(fn () => $action->handle($allocation, $request->validated()), 'تم تسجيل المرتجع.');
    }

    public function waste(RecordMaterialTransactionRequest $request, WorkAssignmentMaterial $allocation, RecordWasteAction $action): RedirectResponse
    {
        return $this->run(fn () => $action->handle($allocation, $request->validated()), 'تم تسجيل الهالك للمراجعة.');
    }

    public function approveWaste(ProductionMaterialTransaction $transaction, ReviewWasteAction $action): RedirectResponse
    {
        return $this->run(fn () => $action->handle($transaction, true), 'تم اعتماد الهالك.');
    }

    public function rejectWaste(ProductionMaterialTransaction $transaction, ReviewWasteAction $action): RedirectResponse
    {
        return $this->run(fn () => $action->handle($transaction, false), 'تم رفض الهالك.');
    }

    public function printHandover(ProductionMaterialTransaction $transaction, DocumentPrinter $printer): View
    {
        $transaction->load(['productionOrder', 'workAssignment.operation', 'worker', 'material', 'recorder']);

        return $printer->render($transaction, 'material_handover');
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
