<?php

namespace App\Http\Requests;

use App\Enums\TrackingMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('access-administration');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $common = ['quantity' => 'prohibited', 'initialized_at' => 'prohibited', 'location' => 'prohibited', 'storage_area_id' => 'prohibited'];

        return $common + match ($this->route('kind')) {
            'categories' => [
                'name' => 'required|string|max:150', 'code' => ['required', 'string', 'max:40', Rule::unique('item_categories')->ignore($this->route('record'))],
                'description' => 'nullable|string|max:10000', 'parent_id' => 'nullable|integer|exists:item_categories,id', 'is_active' => 'required|boolean',
            ],
            'assets' => [
                'asset_reference' => ['required', 'string', 'max:60', Rule::unique('item_assets')->ignore($this->route('record'))],
                'serial_number' => 'nullable|string|max:150', 'description' => 'nullable|string|max:10000', 'condition_notes' => 'nullable|string|max:10000',
                'item_id' => 'prohibited',
            ],
            default => [
                'name' => 'required|string|max:200', 'reference_code' => ['required', 'string', 'max:60', Rule::unique('items')->ignore($this->route('record'))],
                'category_id' => 'required|integer|exists:item_categories,id', 'tracking_mode' => ['required', Rule::enum(TrackingMode::class)],
                'unit_of_measure' => ['required', Rule::in(config('inventory.units'))],
                'description' => 'nullable|string|max:10000', 'brand' => 'nullable|string|max:120', 'model' => 'nullable|string|max:120',
                'notes' => 'nullable|string|max:10000', 'is_active' => 'required|boolean',
            ],
        };
    }
}
