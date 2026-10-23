<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Throwable;

class ImportReviewedCatalog extends Command
{
    protected $signature = 'catalog:import-reviewed {manifest : Berkas JSON katalog yang telah diperiksa} {--apply : Simpan perubahan} {--update-existing : Perbarui produk dengan kode yang sudah ada}';

    protected $description = 'Pratinjau atau impor katalog hasil pemeriksaan dokumen tanpa menggandakan produk dan foto';

    public function handle(): int
    {
        $newFiles = [];
        try {
            $manifest = $this->argument('manifest');
            if (! is_file($manifest)) {
                throw new \RuntimeException('Manifest tidak ditemukan.');
            }
            $payload = json_decode(file_get_contents($manifest), true, 512, JSON_THROW_ON_ERROR);
            Validator::make($payload, [
                'source_document' => ['required', 'string'], 'source_sha256' => ['required', 'regex:/^[a-f0-9]{64}$/'],
                'products' => ['required', 'array', 'min:1', 'max:500'],
                'products.*.code' => ['required', 'string', 'max:40', 'distinct', 'regex:/^[A-Za-z0-9-]+$/'],
                'products.*.name' => ['required', 'string', 'max:150'],
                'products.*.category' => ['required', 'string', 'max:100'],
                'products.*.description' => ['nullable', 'string', 'max:15000'],
                'products.*.specifications' => ['present', 'array', 'max:50'],
                'products.*.specifications.*' => ['string', 'max:1200'],
                'products.*.price' => ['required', 'numeric', 'min:0', 'max:999999999', 'decimal:0,2'],
                'products.*.reference_price_max' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
                'products.*.price_note' => ['nullable', 'string', 'max:255'],
                'products.*.images' => ['required', 'array', 'min:1', 'max:30'],
                'products.*.images.*' => ['required', 'string'],
                'products.*.source_metadata' => ['required', 'array'],
            ])->validate();
            if (! is_file($payload['source_document']) || ! hash_equals($payload['source_sha256'], hash_file('sha256', $payload['source_document']))) {
                throw new \RuntimeException('Sumber dokumen berubah atau tidak ditemukan. Periksa manifest sebelum impor.');
            }
            foreach ($payload['products'] as $row) {
                if (($row['reference_price_max'] ?? null) !== null && (float) $row['reference_price_max'] < (float) $row['price']) {
                    throw new \RuntimeException('Harga maksimum lebih rendah dari harga mulai: '.$row['code']);
                }
                foreach ($row['images'] as $path) {
                    $image = is_file($path) ? @getimagesize($path) : false;
                    if (! $image || ! in_array($image['mime'], ['image/webp', 'image/png', 'image/jpeg'], true) || filesize($path) > 6 * 1024 * 1024) {
                        throw new \RuntimeException('Gambar tidak valid: '.$path);
                    }
                }
            }
            $this->table(['Kode', 'Produk', 'Harga referensi', 'Foto'], array_map(fn (array $row): array => [$row['code'], $row['name'], number_format($row['price'], 0, ',', '.'), count($row['images'])], $payload['products']));
            if (! $this->option('apply')) {
                $this->info('Pratinjau selesai. Tidak ada perubahan data.');

                return self::SUCCESS;
            }
            $fingerprint = hash_file('sha256', $manifest);
            $updated = 0;
            $skipped = 0;
            DB::transaction(function () use ($payload, $fingerprint, &$newFiles, &$updated, &$skipped): void {
                foreach ($payload['products'] as $row) {
                    $product = Product::withTrashed()->where('code', $row['code'])->first();
                    if ($product && (! $this->option('update-existing') || $product->trashed() || ($product->source_metadata['manifest_sha256'] ?? null) === $fingerprint)) {
                        $skipped++;
                        continue;
                    }
                    $category = Category::withTrashed()->firstOrCreate(['name' => $row['category']]);
                    if ($category->trashed()) { $category->restore(); }
                    $product ??= new Product(['is_active' => true]);
                    $metadata = [...$row['source_metadata'], 'manifest_sha256' => $fingerprint, 'document_sha256' => $payload['source_sha256']];
                    $product->fill([
                        'category_id' => $category->id, 'code' => $row['code'], 'name' => $row['name'], 'price' => $row['price'],
                        'description' => $row['description'], 'specifications' => $row['specifications'],
                        'reference_price_max' => $row['reference_price_max'] ?? null, 'price_note' => $row['price_note'] ?? null,
                        'catalog_source' => basename($payload['source_document']), 'source_metadata' => $metadata,
                        'catalog_imported_at' => now(), 'catalog_reviewed' => false,
                    ])->save();
                    foreach ($row['images'] as $position => $path) {
                        $extension = match (getimagesize($path)['mime']) { 'image/webp' => 'webp', 'image/png' => 'png', default => 'jpg' };
                        $target = 'products/catalog/'.strtolower($row['code']).'/'.hash_file('sha256', $path).'.'.$extension;
                        if (! Storage::disk('public')->exists($target)) {
                            if (! Storage::disk('public')->put($target, file_get_contents($path))) {
                                throw new \RuntimeException('Gagal menyimpan gambar produk.');
                            }
                            $newFiles[] = $target;
                        }
                        $product->images()->withTrashed()->firstOrCreate(['path' => $target], ['alt_text' => $row['name'].' — foto '.($position + 1), 'sort_order' => $position]);
                        if ($position === 0) {
                            $product->update(['photo_path' => $target]);
                        }
                    }
                    $updated++;
                }
            });
            $this->info('Impor selesai: '.$updated.' produk disimpan, '.$skipped.' dilewati. Riwayat penjualan tidak diubah.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($newFiles);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
