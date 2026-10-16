<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('access-administration');
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:100', 'role' => ['nullable', Rule::enum(Role::class)], 'status' => 'nullable|in:active,inactive',
            'assignment' => 'nullable|in:assigned,unassigned', 'city_id' => 'nullable|integer|exists:cities,id', 'storage_area_id' => 'nullable|integer|exists:storage_areas,id',
            'sort' => 'nullable|in:name,email,created_at,last_login_at', 'direction' => 'nullable|in:asc,desc',
        ];
    }
}
