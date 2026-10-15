<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveStorageAreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('access-administration');
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => Str::upper(trim($this->input('code')))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('storage_areas')->ignore($this->route('storage_area'))],
            'description' => ['nullable', 'string', 'max:5000'],
            'location_details' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
