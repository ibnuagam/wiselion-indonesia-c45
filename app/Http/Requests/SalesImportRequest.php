<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalesImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return ['file' => ['required', 'file', 'mimes:csv,txt', 'extensions:csv', 'max:1024'],
            'data_origin' => ['required', Rule::in(['real', 'simulation'])], 'confirmed' => ['accepted']];
    }
}
