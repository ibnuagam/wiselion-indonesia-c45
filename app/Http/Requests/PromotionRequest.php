<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'sale_enabled' => ['required', 'boolean'],
            'sale_title' => ['nullable', 'string', 'max:100'],
            'sale_price' => [Rule::requiredIf($this->boolean('sale_enabled')), 'nullable', 'numeric', 'min:0', 'max:999999999', 'decimal:0,2'],
            'sale_starts_on' => ['nullable', 'date_format:Y-m-d'],
            'sale_ends_on' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('sale_starts_on'), 'after_or_equal:sale_starts_on')],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->boolean('sale_enabled') && ! $validator->errors()->has('sale_price') && (float) $this->sale_price >= (float) $this->route('product')->price) {
                $validator->errors()->add('sale_price', 'Harga promo harus lebih rendah dari harga normal produk.');
            }
        }];
    }
}
