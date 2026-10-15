<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChangeAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('access-administration');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'user_id' => ['present', 'nullable', 'integer', 'exists:users,id'],
            'expected_assignment_id' => ['present', 'nullable', 'integer', 'min:1'],
        ];
    }
}
