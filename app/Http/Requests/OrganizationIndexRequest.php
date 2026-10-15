<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrganizationIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('access-administration');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'employees_page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
