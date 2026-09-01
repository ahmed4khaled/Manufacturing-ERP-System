<?php

namespace App\Http\Controllers;

use App\Actions\Cutover\AddOpeningBalanceAction;
use App\Actions\Cutover\CreateCutoverBatchAction;
use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\CutoverBatch;
use App\Models\CutoverOpeningBalance;
use App\Models\Material;
use App\Models\Partner;
use App\Models\ProductionOrder;
use App\Models\ProductVariant;
use App\Models\Worker;
use App\Services\Cutover\CutoverCsvImportService;
use App\Services\Cutover\CutoverPostingService;
use App\Services\Cutover\CutoverReconciliationService;
use App\Services\Cutover\CutoverReversalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class CutoverController extends Controller
{
    public function index(): View
    {
        return view('cutover.index', [
            'batches' => CutoverBatch::query()->withCount('balances')->latest('cutover_date')->paginate(20),
        ]);
    }

    public function store(Request $request, CreateCutoverBatchAction $action): RedirectResponse
    {
        $data = $request->validate([
            'cutover_date' => ['required', 'date'],
            'reference' => ['required', 'string', 'max:120', 'unique:cutover_batches,reference'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $batch = $action->handle($data, $request->user()?->id);

        return redirect()->route('cutover.show', $batch)->with('success', 'تم إنشاء دفعة الـCutover كمسودة وتجهيز الفترات الشهرية.');
    }

    public function show(CutoverBatch $cutoverBatch, CutoverReconciliationService $reconciliation): View
    {
        $cutoverBatch->load([
            'balances.account', 'balances.material', 'balances.productVariant.product',
            'balances.productionOrder', 'balances.worker', 'balances.partner',
            'journalEntry.lines.account', 'reversalJournalEntry', 'creator', 'poster', 'reverser',
        ]);
        $reserved = AccountMapping::query()->whereIn('key', [
            'accounts_receivable', 'accounts_payable', 'cash', 'bank', 'raw_material_inventory',
            'finished_goods_inventory', 'work_in_progress', 'production_wages_payable',
            'partner_capital', 'partner_current', 'partner_withdrawals', 'opening_balance_clearing',
        ])->pluck('account_id');

        return view('cutover.show', [
            'batch' => $cutoverBatch,
            'report' => $reconciliation->report($cutoverBatch),
            'cashAccounts' => Account::posting()->whereDoesntHave('children')->whereIn('subtype', ['cash', 'bank'])->orderBy('code')->get(),
            'otherAccounts' => Account::posting()->whereDoesntHave('children')->whereNotIn('id', $reserved)->orderBy('code')->get(),
            'materials' => Material::query()->orderBy('code')->get(),
            'variants' => ProductVariant::query()->with(['product', 'color', 'size'])->orderBy('sku')->get(),
            'productionOrders' => ProductionOrder::query()->whereNotIn('status', ['completed', 'cancelled'])->orderBy('order_number')->get(),
            'workers' => Worker::query()->orderBy('code')->get(),
            'partners' => Partner::query()->where('is_active', true)->orderBy('code')->get(),
            'types' => CutoverOpeningBalance::TYPES,
        ]);
    }

    public function storeBalance(Request $request, CutoverBatch $cutoverBatch, AddOpeningBalanceAction $action): RedirectResponse
    {
        $action->handle($cutoverBatch, $request->all());

        return back()->with('success', 'تمت إضافة الرصيد الافتتاحي إلى المسودة.');
    }

    public function destroyBalance(CutoverBatch $cutoverBatch, CutoverOpeningBalance $openingBalance): RedirectResponse
    {
        abort_unless($openingBalance->cutover_batch_id === $cutoverBatch->id, 404);
        $openingBalance->delete();

        return back()->with('success', 'تم حذف السطر من المسودة.');
    }

    public function importForm(CutoverBatch $cutoverBatch, CutoverCsvImportService $import): View
    {
        return view('cutover.import', [
            'batch' => $cutoverBatch,
            'types' => $import->types(),
            'preview' => session($this->previewKey($cutoverBatch)),
        ]);
    }

    public function previewImport(Request $request, CutoverBatch $cutoverBatch, CutoverCsvImportService $import): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in($import->types())],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);
        $preview = $import->preview($cutoverBatch, $data['type'], $data['file']);
        session([$this->previewKey($cutoverBatch) => $preview]);

        return back()->with('success', 'تمت قراءة الملف للمعاينة فقط؛ لم يتم حفظ أي رصيد بعد.');
    }

    public function commitImport(CutoverBatch $cutoverBatch, CutoverCsvImportService $import): RedirectResponse
    {
        $preview = session($this->previewKey($cutoverBatch));
        abort_unless(is_array($preview), 422, 'لا توجد معاينة جاهزة للحفظ.');
        $count = $import->commit($cutoverBatch, $preview);
        session()->forget($this->previewKey($cutoverBatch));

        return redirect()->route('cutover.show', $cutoverBatch)->with('success', "تم استيراد {$count} سطرًا إلى المسودة.");
    }

    public function template(string $type, CutoverCsvImportService $import): Response
    {
        return response($import->template($type), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=Cutover_{$type}.csv",
        ]);
    }

    public function post(Request $request, CutoverBatch $cutoverBatch, CutoverPostingService $posting): RedirectResponse
    {
        $posting->post($cutoverBatch, $request->user()?->id);

        return back()->with('success', 'تم ترحيل دفعة الـCutover وإنشاء القيد وطبقات FIFO. راجع المطابقة قبل إعلان الجاهزية.');
    }

    public function reverse(Request $request, CutoverBatch $cutoverBatch, CutoverReversalService $reversal): RedirectResponse
    {
        $data = $request->validate(['reversal_date' => ['required', 'date', 'after_or_equal:'.$cutoverBatch->cutover_date->toDateString()]]);
        $reversal->reverse($cutoverBatch, $data['reversal_date'], $request->user()?->id);

        return back()->with('success', 'تم عكس القيد والآثار الافتتاحية بأمان.');
    }

    public function reconciliation(CutoverBatch $cutoverBatch, CutoverReconciliationService $service): View
    {
        return view('cutover.reconciliation', ['batch' => $cutoverBatch, 'report' => $service->report($cutoverBatch)]);
    }

    private function previewKey(CutoverBatch $batch): string
    {
        return 'cutover_import_preview_'.$batch->id;
    }
}
