<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('access-administration');
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        if ($this->routeIs('users.role')) {
            return ['role' => ['required', Rule::enum(Role::class)]];
        }
        if ($this->routeIs('users.status')) {
            return ['is_active' => 'required|boolean'];
        }
        if ($this->routeIs('users.invite', 'users.setup-link')) {
            return [];
        }
        $rules = ['name' => 'required|string|max:255', 'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($this->route('user'))],
            'phone' => 'nullable|string|max:40', 'administrative_notes' => 'nullable|string|max:10000'];
        if ($this->routeIs('users.store')) {
            $rules += ['role' => ['sometimes', Rule::enum(Role::class)], 'is_active' => 'sometimes|boolean'];
        }

        return $rules;
    }
}
