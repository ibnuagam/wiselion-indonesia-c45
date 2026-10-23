<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SalesImport;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SalesImportService
{
    public const HEADERS = ['product_code', 'period', 'units_sold', 'order_frequency', 'unit_price', 'reference_label', 'label_basis'];

    public function preview(UploadedFile $file, string $origin, User $user): SalesImport
    {
        $handle = fopen($file->getRealPath(), 'r');
        try {
            $first = fgets($handle);
            if ($first === false) {
                throw ValidationException::withMessages(['file' => 'CSV kosong. Gunakan template yang tersedia.']);
            }
            $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
            rewind($handle);
            $headers = fgetcsv($handle, 0, $delimiter, '"', '');
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0] ?? '');
            if ($headers !== self::HEADERS) {
                throw ValidationException::withMessages(['file' => 'Kolom CSV harus persis mengikuti template: '.implode(', ', self::HEADERS)]);
            }
            $rows = $errors = [];
            $line = 1;
            while (($cells = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
                $line++;
                if ($cells === [null] || $cells === ['']) {
                    continue;
                }
                if (count($rows) >= 1000) {
                    throw ValidationException::withMessages(['file' => 'Maksimal 1000 observasi per file CSV.']);
                }
                if (count($cells) !== count(self::HEADERS) || ! mb_check_encoding(implode('', $cells), 'UTF-8')) {
                    $errors[] = 'Baris '.$line.': jumlah kolom tidak sesuai atau teks bukan UTF-8.';
                    $rows[] = ['line' => $line, 'invalid' => true];
                    continue;
                }
                $rows[] = ['line' => $line, ...array_combine(self::HEADERS, array_map(fn ($value): string => trim($value ?? ''), $cells))];
            }
        } finally {
            fclose($handle);
        }
        if ($rows === []) {
            throw ValidationException::withMessages(['file' => 'CSV belum berisi data. Template hanya berisi nama kolom.']);
        }
        $validated = $this->validateRows($rows, $origin);

        return SalesImport::create(['user_id' => $user->id, 'source_name' => mb_substr(basename($file->getClientOriginalName()), 0, 180),
            'checksum' => hash_file('sha256', $file->getRealPath()), 'data_origin' => $origin, 'status' => 'preview',
            'rows' => $rows, 'errors' => array_slice([...$errors, ...$validated['errors']], 0, 100), 'row_count' => count($rows)]);
    }

    public function commit(SalesImport $batch, User $user): void
    {
        DB::transaction(function () use ($batch, $user): void {
            $locked = SalesImport::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'committed') {
                return;
            }
            if ($locked->errors !== []) {
                throw ValidationException::withMessages(['import' => 'Pratinjau masih memiliki kesalahan. Perbaiki CSV lalu unggah ulang.']);
            }
            $validated = $this->validateRows($locked->rows, $locked->data_origin);
            if ($validated['errors'] !== []) {
                throw ValidationException::withMessages(['import' => implode(' ', array_slice($validated['errors'], 0, 5))]);
            }
            foreach ($validated['records'] as $data) {
                Sale::create([...$data, 'sales_import_id' => $locked->id,
                    'labelled_by' => $data['reference_label'] ? $user->id : null, 'labelled_at' => $data['reference_label'] ? now() : null]);
            }
            $locked->update(['status' => 'committed', 'committed_at' => now()]);
        });
    }

    private function validateRows(array $rows, string $origin): array
    {
        $products = Product::whereIn('code', array_column($rows, 'product_code'))->get(['id', 'code'])->keyBy('code');
        $existing = Sale::withTrashed()->whereIn('product_id', $products->pluck('id'))->get(['product_id', 'month', 'year'])->keyBy(fn (Sale $sale): string => $sale->product_id.'-'.sprintf('%04d-%02d', $sale->year, $sale->month));
        $errors = $records = $seen = [];
        foreach ($rows as $row) {
            $line = $row['line'];
            if ($row['invalid'] ?? false) {
                continue;
            }
            $validator = Validator::make($row, [
                'product_code' => ['required', 'string', 'max:40'], 'period' => ['required', 'date_format:Y-m', 'after_or_equal:2000-01', 'before_or_equal:2100-12'],
                'units_sold' => ['required', 'integer', 'between:0,1000000'], 'order_frequency' => ['required', 'integer', 'between:0,1000000'],
                'unit_price' => ['required', 'regex:/\A\d{1,9}(?:\.\d{1,2})?\z/'],
                'reference_label' => ['nullable', Rule::in(C45Service::LABELS)],
                'label_basis' => ['nullable', 'string', 'max:500', Rule::requiredIf(($row['reference_label'] ?? '') !== '')],
            ]);
            if ($validator->fails()) {
                $errors[] = 'Baris '.$line.': '.implode(' ', $validator->errors()->all());
                continue;
            }
            $product = $products->get($row['product_code']);
            $key = ($product?->id ?? 0).'-'.$row['period'];
            if (! $product) {
                $errors[] = 'Baris '.$line.': kode produk tidak ditemukan atau produk diarsipkan.';
                continue;
            }
            if (isset($seen[$key]) || $existing->has($key)) {
                $errors[] = 'Baris '.$line.': produk dan bulan duplikat (termasuk arsip). Edit atau pulihkan catatan yang sudah ada.';
                continue;
            }
            $seen[$key] = true;
            $units = (int) $row['units_sold'];
            $orders = (int) $row['order_frequency'];
            if (($units === 0 && $orders !== 0) || ($units > 0 && ($orders === 0 || $orders > $units))) {
                $errors[] = 'Baris '.$line.': frekuensi order harus 0 saat unit 0, atau 1 sampai jumlah unit.';
                continue;
            }
            [$year, $month] = array_map('intval', explode('-', $row['period']));
            $price = number_format((float) $row['unit_price'], 2, '.', '');
            $totalCents = (int) round((float) $price * 100) * $units;
            $records[] = ['product_id' => $product->id, 'year' => $year, 'month' => $month, 'units_sold' => $units, 'order_frequency' => $orders,
                'unit_price' => $price, 'total_revenue' => intdiv($totalCents, 100).'.'.str_pad((string) ($totalCents % 100), 2, '0', STR_PAD_LEFT),
                'data_origin' => $origin, 'reference_label' => $row['reference_label'] ?: null, 'label_basis' => $row['reference_label'] ? $row['label_basis'] : null];
        }

        return compact('records', 'errors');
    }
}
