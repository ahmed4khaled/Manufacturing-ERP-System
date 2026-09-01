<?php

namespace App\Services\Cutover;

use App\Actions\Cutover\AddOpeningBalanceAction;
use App\Models\Account;
use App\Models\CutoverBatch;
use App\Models\Material;
use App\Models\Partner;
use App\Models\ProductionOrder;
use App\Models\ProductVariant;
use App\Models\Worker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CutoverCsvImportService
{
    private const HEADERS = [
        'cash_bank' => ['account_code', 'amount', 'reference', 'description'],
        'material' => ['material_code', 'quantity', 'unit_cost', 'reference', 'description'],
        'finished_goods' => ['sku', 'quantity', 'unit_cost', 'reference', 'description'],
        'wip' => ['production_order', 'amount', 'reference', 'description'],
        'worker_payable' => ['worker_code', 'production_order', 'amount', 'reference', 'description'],
        'partner' => ['partner_code', 'balance_type', 'amount', 'reference', 'description'],
        'other' => ['account_code', 'amount', 'reference', 'description'],
    ];

    public function __construct(private readonly AddOpeningBalanceAction $add) {}

    public function preview(CutoverBatch $batch, string $type, UploadedFile $file): array
    {
        if (! isset(self::HEADERS[$type])) {
            throw ValidationException::withMessages(['type' => 'نوع ملف الاستيراد غير مدعوم.']);
        }
        $handle = fopen($file->getRealPath(), 'rb');
        if (! $handle) {
            throw ValidationException::withMessages(['file' => 'تعذر قراءة ملف CSV.']);
        }
        $header = fgetcsv($handle);
        if (! is_array($header)) {
            fclose($handle);
            throw ValidationException::withMessages(['file' => 'ملف CSV فارغ.']);
        }
        $header = array_map(fn ($value) => trim(str_replace("\xEF\xBB\xBF", '', (string) $value)), $header);
        if ($header !== self::HEADERS[$type]) {
            fclose($handle);
            throw ValidationException::withMessages(['file' => 'عناوين الأعمدة لا تطابق القالب الرسمي لهذا النوع.']);
        }

        $rows = [];
        $errors = [];
        $warnings = [];
        $seen = [];
        $rowNumber = 1;
        while (($values = fgetcsv($handle)) !== false) {
            $rowNumber++;
            if (count(array_filter($values, fn ($value) => filled($value))) === 0) {
                continue;
            }
            if (count($values) !== count($header)) {
                $errors[] = ['row' => $rowNumber, 'message' => 'عدد أعمدة السطر لا يطابق القالب الرسمي.'];

                continue;
            }
            $row = array_combine($header, $values);
            try {
                $resolved = $this->resolve($type, $row);
                if (isset($seen[$resolved['layer_reference']])) {
                    throw ValidationException::withMessages(['reference' => 'مرجع مكرر داخل الملف.']);
                }
                $seen[$resolved['layer_reference']] = true;
                $prepared = $this->add->prepare($batch, $resolved);
                $rows[] = $prepared;
                if (in_array($type, ['material', 'finished_goods'], true) && $prepared['unit_cost'] === null) {
                    $warnings[] = ['row' => $rowNumber, 'message' => 'طبقة غير مُقيّمة؛ لن تكون الدفعة جاهزة للتشغيل قبل تقييمها.'];
                }
            } catch (ValidationException $exception) {
                $errors[] = ['row' => $rowNumber, 'message' => collect($exception->errors())->flatten()->first()];
            }
        }
        fclose($handle);

        return [
            'type' => $type,
            'rows' => $rows,
            'total' => count($rows) + count($errors),
            'valid' => count($rows),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    public function commit(CutoverBatch $batch, array $preview): int
    {
        if (($preview['errors'] ?? []) !== []) {
            throw ValidationException::withMessages(['import' => 'عالج أخطاء المعاينة قبل الحفظ.']);
        }

        return DB::transaction(function () use ($batch, $preview): int {
            $count = 0;
            foreach ($preview['rows'] ?? [] as $row) {
                $this->add->handle($batch, $row);
                $count++;
            }

            return $count;
        });
    }

    public function template(string $type): string
    {
        if (! isset(self::HEADERS[$type])) {
            throw ValidationException::withMessages(['type' => 'نوع القالب غير مدعوم.']);
        }
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, self::HEADERS[$type]);
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        return $content;
    }

    public function types(): array
    {
        return array_keys(self::HEADERS);
    }

    private function resolve(string $type, array $row): array
    {
        $base = [
            'type' => $type,
            'layer_reference' => trim((string) ($row['reference'] ?? '')),
            'description' => $row['description'] ?? null,
        ];

        return match ($type) {
            'cash_bank', 'other' => $base + [
                'account_id' => Account::query()->where('code', trim((string) $row['account_code']))->value('id'),
                'amount' => $row['amount'] ?? null,
            ],
            'material' => $base + [
                'material_id' => Material::withTrashed()->where('code', trim((string) $row['material_code']))->value('id'),
                'quantity' => $row['quantity'] ?? null,
                'unit_cost' => filled($row['unit_cost'] ?? null) ? $row['unit_cost'] : null,
            ],
            'finished_goods' => $base + [
                'product_variant_id' => ProductVariant::withTrashed()->where('sku', trim((string) $row['sku']))->value('id'),
                'quantity' => $row['quantity'] ?? null,
                'unit_cost' => filled($row['unit_cost'] ?? null) ? $row['unit_cost'] : null,
            ],
            'wip' => $base + [
                'production_order_id' => ProductionOrder::withTrashed()->where('order_number', trim((string) $row['production_order']))->value('id'),
                'amount' => $row['amount'] ?? null,
            ],
            'worker_payable' => $base + [
                'worker_id' => Worker::withTrashed()->where('code', trim((string) $row['worker_code']))->value('id'),
                'production_order_id' => filled($row['production_order'] ?? null)
                    ? ProductionOrder::withTrashed()->where('order_number', trim((string) $row['production_order']))->value('id')
                    : null,
                'amount' => $row['amount'] ?? null,
            ],
            'partner' => $base + [
                'partner_id' => Partner::query()->where('code', trim((string) $row['partner_code']))->value('id'),
                'partner_balance_type' => $row['balance_type'] ?? null,
                'amount' => $row['amount'] ?? null,
            ],
        };
    }
}
