<?php

namespace App\Http\Controllers;

use App\Actions\Worker\CreateWorkerAction;
use App\Actions\Worker\DeleteWorkerAction;
use App\Actions\Worker\UpdateWorkerAction;
use App\Http\Requests\Worker\StoreWorkerRequest;
use App\Http\Requests\Worker\UpdateWorkerRequest;
use App\Models\Worker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WorkerController extends Controller
{
    public function index(Request $request): View
    {
        $workers = Worker::query()->search($request->input('search'))->filterStatus($request->input('status'))->latest()->paginate(15)->withQueryString();

        return view('workers.index', compact('workers'));
    }

    public function create(): View
    {
        return view('workers.create');
    }

    public function store(StoreWorkerRequest $request, CreateWorkerAction $action): RedirectResponse
    {
        $worker = $action->handle($request->validated());

        return redirect()->route('workers.show', $worker)->with('success', 'تم إنشاء العامل بنجاح.');
    }

    public function show(Worker $worker): View
    {
        $worker->loadCount('workAssignments')->load(['workAssignments.productionOrder', 'workAssignments.operation']);

        return view('workers.show', compact('worker'));
    }

    public function edit(Worker $worker): View
    {
        return view('workers.edit', compact('worker'));
    }

    public function update(UpdateWorkerRequest $request, Worker $worker, UpdateWorkerAction $action): RedirectResponse
    {
        $action->handle($worker, $request->validated());

        return redirect()->route('workers.show', $worker)->with('success', 'تم تحديث العامل بنجاح.');
    }

    public function destroy(Worker $worker, DeleteWorkerAction $action): RedirectResponse
    {
        try {
            $action->handle($worker);
        } catch (ValidationException $e) {
            return back()->with('error', $e->validator->errors()->first());
        }

        return redirect()->route('workers.index')->with('success', 'تم حذف العامل بنجاح مع الاحتفاظ بسجلاته التاريخية.');
    }
}
