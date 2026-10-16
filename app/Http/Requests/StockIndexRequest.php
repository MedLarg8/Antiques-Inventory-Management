<?php

namespace App\Http\Requests;

use App\Enums\StockOperation;
use App\Enums\StockRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->is_active;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:100', 'employee' => 'nullable|string|max:100', 'status' => ['nullable', Rule::enum(StockRequestStatus::class)],
            'operation_type' => ['nullable', Rule::enum(StockOperation::class)], 'storage_area_id' => 'nullable|integer|exists:storage_areas,id', 'city_id' => 'nullable|integer|exists:cities,id',
            'from' => 'nullable|date_format:Y-m-d', 'to' => ['nullable', 'date_format:Y-m-d', ...($this->filled('from') ? ['after_or_equal:from'] : [])], 'scope' => 'nullable|in:mine,execution',
        ];
    }
}
