<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveCityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('access-administration');
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => Str::upper(trim($this->input('code'))) ?: null]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:30', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('cities')->ignore($this->route('city'))],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
