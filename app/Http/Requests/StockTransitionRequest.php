<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockTransitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->is_active;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'rejection_reason' => $this->routeIs('stock-requests.reject') ? 'required|string|max:10000' : 'prohibited',
            'confirmed' => $this->routeIs('stock-requests.execute') ? 'required|accepted' : 'prohibited',
            'lines' => 'prohibited', 'quantity' => 'prohibited', 'status' => 'prohibited', 'operation_type' => 'prohibited', 'storage_area_id' => 'prohibited', 'initialized_at' => 'prohibited',
        ];
    }
}
