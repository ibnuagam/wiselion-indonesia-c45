<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use App\Services\C45Service;

class SaleRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isAdmin() ?? false; }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at'),
                Rule::unique('sales', 'product_id')->where(fn ($query) => $query->where('month', $this->input('month'))->where('year', $this->input('year')))->ignore($this->route('sale'))],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'units_sold' => ['required', 'integer', 'between:0,1000000'],
            'order_frequency' => ['required', 'integer', 'between:0,1000000'],
            'unit_price' => ['required', 'numeric', 'between:0,999999999', 'decimal:0,2'],
            'confirmed' => ['accepted'],
            'data_origin' => ['sometimes', Rule::in(['real', 'simulation', 'legacy'])],
            'reference_label' => ['nullable', Rule::in(C45Service::LABELS)],
            'label_basis' => ['nullable', 'string', 'max:500', 'required_with:reference_label'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['units_sold', 'order_frequency'])) { return; }
            $units = $this->integer('units_sold');
            $frequency = $this->integer('order_frequency');
            if (($units === 0 && $frequency !== 0) || ($units > 0 && ($frequency === 0 || $frequency > $units))) {
                $validator->errors()->add('order_frequency', 'Frekuensi harus 0 jika unit 0; jika ada penjualan, frekuensi minimal 1 dan tidak melebihi unit terjual.');
            }
        }];
    }

    public function messages(): array
    {
        return ['product_id.unique' => 'Produk sudah memiliki data pada bulan dan tahun ini, termasuk arsip. Edit data yang ada atau pulihkan dari arsip.', 'confirmed.accepted' => 'Konfirmasikan bahwa data hanya mencakup penjualan lunas, tidak batal, dan tidak retur.'];
    }
}
