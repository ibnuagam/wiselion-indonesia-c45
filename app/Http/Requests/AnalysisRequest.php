<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnalysisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:120'], 'start' => ['required', 'date_format:Y-m', 'after_or_equal:2000-01', 'before_or_equal:2100-12'],
            'end' => ['required', 'date_format:Y-m', 'after_or_equal:start', 'before_or_equal:2100-12'],
            'test_start' => ['required', 'date_format:Y-m', 'after:start', 'before_or_equal:end'],
            'data_origin' => ['required', Rule::in(['real', 'simulation'])],
            'label_policy' => ['required', 'string', 'min:20', 'max:1500'], 'confirmed' => ['accepted']];
    }
}
