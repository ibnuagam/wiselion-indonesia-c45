<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HeadlineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'eyebrow' => ['nullable', 'string', 'max:80'],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:600'],
            'button_text' => ['required', 'string', 'max:40'],
            'product_id' => ['nullable', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')->where('is_active', true)],
            'theme' => ['required', Rule::in(['dark', 'light'])],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_published' => ['required', 'boolean'],
            'starts_on' => ['nullable', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('starts_on'), 'after_or_equal:starts_on')],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=800,min_height=300,max_width=6144,max_height=6144'],
            'mobile_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=320,min_height=400,max_width=6144,max_height=6144'],
            'remove_image' => ['sometimes', 'boolean'],
            'remove_mobile_image' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['ends_on.after_or_equal' => 'Tanggal akhir harus sama atau setelah tanggal mulai.', 'image.dimensions' => 'Banner desktop minimal 800 × 300 piksel.', 'image.max' => 'Banner desktop maksimal 2 MB.', 'mobile_image.max' => 'Banner ponsel maksimal 2 MB.'];
    }
}
