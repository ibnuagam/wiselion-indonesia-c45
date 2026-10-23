<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProductRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isAdmin() ?? false; }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:40', Rule::unique('products', 'code')->ignore($this->route('product'))],
            'name' => ['required', 'string', 'max:150'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'price' => ['required', 'numeric', 'min:0', 'max:999999999', 'decimal:0,2'],
            'description' => ['nullable', 'string', 'max:15000'],
            'specifications_text' => ['nullable', 'string', 'max:20000'],
            'reference_price_max' => ['nullable', 'numeric', 'min:0', 'max:999999999', 'gte:price'],
            'price_note' => ['nullable', 'string', 'max:255'],
            'catalog_reviewed' => ['sometimes', 'boolean'],
            'gallery' => ['sometimes', 'array', 'max:6'],
            'gallery.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=64,min_height=64,max_width=5000,max_height=5000'],
            'remove_gallery' => ['sometimes', 'array'],
            'remove_gallery.*' => ['integer', 'distinct', Rule::exists('product_images', 'id')->where('product_id', $this->route('product')?->id ?? 0)->whereNull('deleted_at')],
            'is_active' => ['required', 'boolean'],
            'is_new' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=64,min_height=64,max_width=5000,max_height=5000'],
            'remove_photo' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['code.unique' => 'Kode produk sudah digunakan, termasuk produk dalam arsip.', 'photo.max' => 'Ukuran foto maksimal 2 MB.', 'photo.mimes' => 'Foto harus berformat JPG, PNG, atau WebP.'];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $validator->errors()->has('specifications_text')) {
                $keys = [];
                foreach (preg_split('/\R/u', $this->input('specifications_text') ?? '') as $line) {
                    if (trim($line) === '') { continue; }
                    $parts = explode(':', $line, 2);
                    if (count($parts) !== 2 || trim($parts[0]) === '' || trim($parts[1]) === '' || mb_strlen(trim($parts[0])) > 80 || mb_strlen(trim($parts[1])) > 1200 || in_array(trim($parts[0]), $keys, true)) {
                        $validator->errors()->add('specifications_text', 'Setiap spesifikasi harus memakai format Label: Isi, dengan label unik maksimal 80 karakter.');
                        break;
                    }
                    $keys[] = trim($parts[0]);
                }
                if (count($keys) > 50) { $validator->errors()->add('specifications_text', 'Maksimal 50 spesifikasi.'); }
            }
            $galleryFiles = $this->file('gallery', []);
            $size = array_sum(array_map(fn ($file): int => $file instanceof \Illuminate\Http\UploadedFile ? $file->getSize() : 0, [...(is_array($galleryFiles) ? $galleryFiles : []), $this->file('photo')]));
            if ($size > 6 * 1024 * 1024) { $validator->errors()->add('gallery', 'Total unggahan foto maksimal 6 MB per penyimpanan.'); }
        }];
    }
}
