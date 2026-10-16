<?php

namespace App\Http\Requests;

use App\Enums\StockOperation;
use App\Rules\StockQuantity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveStockDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view-own-storage');
    }

    /** @return array{operation_type:string,reason:string,source_or_destination:?string,lines:array<int,array{item_id:int,quantity:string}>} */
    public function draftData(): array
    {
        return [
            'operation_type' => $this->string('operation_type')->toString(),
            'reason' => $this->string('reason')->toString(),
            'source_or_destination' => $this->filled('source_or_destination') ? $this->string('source_or_destination')->toString() : null,
            'lines' => array_map(fn (array $line) => ['item_id' => (int) $line['item_id'], 'quantity' => (string) $line['quantity']], $this->validated('lines')),
        ];
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'operation_type' => ['required', Rule::enum(StockOperation::class)], 'reason' => 'required|string|max:10000', 'source_or_destination' => 'nullable|string|max:500',
            'lines' => 'present|array|max:100', 'lines.*.item_id' => 'required|integer|distinct|exists:items,id', 'lines.*.quantity' => ['required', new StockQuantity],
            'storage_area_id' => 'prohibited', 'requested_by' => 'prohibited', 'status' => 'prohibited', 'reviewed_by' => 'prohibited', 'executed_by' => 'prohibited', 'quantity' => 'prohibited', 'initialized_at' => 'prohibited',
        ];
    }
}
