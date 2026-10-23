<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductColorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');
        $this->merge(['name' => is_string($name) ? Str::squish($name) : $name,
            'name_key' => is_string($name) ? mb_strtolower(Str::squish($name)) : null]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'name_key' => ['required', 'string', 'max:80', Rule::unique('product_colors', 'name_key')->where('product_id', $this->route('product')->id)->ignore($this->route('color'))],
            'color_hex' => ['required', 'string', 'regex:/\A#[0-9a-fA-F]{6}\z/'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['required', 'boolean'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=64,min_height=64,max_width=5000,max_height=5000'],
            'remove_photo' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['name_key.unique' => 'Warna ini sudah ada pada produk ini, termasuk dalam arsip. Pulihkan warna yang diarsipkan jika diperlukan.',
            'color_hex.regex' => 'Gunakan kode warna heksadesimal, misalnya #233C69.',
            'photo.max' => 'Ukuran foto warna maksimal 2 MB.',
            'photo.mimes' => 'Foto warna harus berupa JPG, PNG, atau WebP.'];
    }
}
