<?php

namespace App\Actions;

use App\Enums\StockOperation;
use App\Enums\StockRequestStatus;
use App\Enums\TrackingMode;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\StockRequest;
use App\Models\StockRequestEvent;
use App\Models\StockRequestItem;
use App\Models\StorageArea;
use App\Models\StorageAreaInventory;
use App\Models\User;
use App\Rules\StockQuantity;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StockWorkflow
{
    /** @param array{operation_type:string,reason:string,source_or_destination?:?string,lines:array<int,array{item_id:int,quantity:string}>} $data */
    public function create(User $actor, array $data): StockRequest
    {
        return $this->transaction(function () use ($actor, $data) {
            $user = $this->actor($actor);
            Gate::forUser($user)->authorize('create', StockRequest::class);
            $assigned = $user->currentStorageArea()->firstOrFail();
            $area = StorageArea::lockForUpdate()->findOrFail($assigned->id);
            if (! $area->is_active || ! $area->currentAssignment()->where('user_id', $user->id)->exists()) {
                $this->fail('Your depot responsibility changed. Refresh before creating a request.');
            }
            $request = new StockRequest;
            $request->forceFill(['request_number' => 'SR-'.Str::ulid(), 'storage_area_id' => $area->id, 'requested_by' => $user->id, 'status' => StockRequestStatus::Draft,
                'operation_type' => $data['operation_type'], 'reason' => $data['reason'], 'source_or_destination' => $data['source_or_destination'] ?? null])->save();
            $this->saveLines($request, $data['lines']);
            $this->event($request, $user, 'draft_created', null);

            return $request;
        });
    }

    /** @param array{operation_type:string,reason:string,source_or_destination?:?string,lines:array<int,array{item_id:int,quantity:string}>} $data */
    public function update(User $actor, StockRequest $request, array $data): void
    {
        $this->transaction(function () use ($actor, $request, $data) {
            [$locked,$user] = $this->lock($request, $actor);
            $this->expect($locked, StockRequestStatus::Draft);
            Gate::forUser($user)->authorize('update', $locked);
            $before = $locked->only(['operation_type', 'reason', 'source_or_destination']);
            $old = $locked->lines()->get()->map(fn (StockRequestItem $line) => [$line->item_id, $line->quantity])->all();
            $locked->forceFill(['operation_type' => $data['operation_type'], 'reason' => $data['reason'], 'source_or_destination' => $data['source_or_destination'] ?? null])->save();
            $this->saveLines($locked, $data['lines']);
            $new = $locked->lines()->get()->map(fn (StockRequestItem $line) => [$line->item_id, $line->quantity])->all();
            if ($before !== $locked->only(['operation_type', 'reason', 'source_or_destination']) || $old !== $new) {
                $this->event($locked, $user, 'draft_updated', StockRequestStatus::Draft);
            }
        });
    }

    public function submit(User $actor, StockRequest $request): void
    {
        $this->transaction(function () use ($actor, $request) {
            [$locked,$user] = $this->lock($request, $actor);
            $this->expect($locked, StockRequestStatus::Draft);
            Gate::forUser($user)->authorize('update', $locked);
            $lines = $this->checkedLines($locked, true);
            if (trim($locked->reason) === '') {
                $this->fail('A reason is required before submission.');
            }
            if ($locked->operation_type === StockOperation::Exit) {
                $this->availability($locked, $lines);
            }
            $locked->forceFill(['status' => StockRequestStatus::Pending, 'submitted_at' => now()])->save();
            $this->event($locked, $user, 'submitted', StockRequestStatus::Draft);
        });
    }

    public function approve(User $actor, StockRequest $request): void
    {
        $this->review($actor, $request, null);
    }

    public function reject(User $actor, StockRequest $request, string $reason): void
    {
        if (trim($reason) === '') {
            $this->fail('A rejection reason is required.');
        }
        $this->review($actor, $request, $reason);
    }

    private function review(User $actor, StockRequest $request, ?string $reason): void
    {
        $this->transaction(function () use ($actor, $request, $reason) {
            [$locked,$user] = $this->lock($request, $actor);
            $this->expect($locked, StockRequestStatus::Pending);
            Gate::forUser($user)->authorize('review', $locked);
            $locked->forceFill(['status' => $reason === null ? StockRequestStatus::Approved : StockRequestStatus::Rejected,
                'reviewed_by' => $user->id, 'reviewed_at' => now(), 'rejection_reason' => $reason])->save();
            $this->event($locked, $user, $reason === null ? 'approved' : 'rejected', StockRequestStatus::Pending, $reason);
        });
    }

    public function cancel(User $actor, StockRequest $request): void
    {
        $this->transaction(function () use ($actor, $request) {
            [$locked,$user] = $this->lock($request, $actor);
            $this->expect($locked, StockRequestStatus::Draft);
            Gate::forUser($user)->authorize('cancel', $locked);
            $locked->forceFill(['status' => StockRequestStatus::Cancelled])->save();
            $this->event($locked, $user, 'cancelled', StockRequestStatus::Draft);
        });
    }

    public function execute(User $actor, StockRequest $request): void
    {
        $this->transaction(function () use ($actor, $request) {
            [$locked,$user] = $this->lock($request, $actor);
            $this->expect($locked, StockRequestStatus::Approved);
            Gate::forUser($user)->authorize('execute', $locked);
            $lines = $this->checkedLines($locked, false);
            $balances = $this->availability($locked, $lines);
            $now = now();
            foreach ($lines as $line) {
                $balance = $balances->get($line->item_id);
                $before = $balance->quantity;
                $delta = $locked->operation_type === StockOperation::Entry ? $line->quantity : bcsub('0', $line->quantity, 3);
                $after = bcadd($before, $delta, 3);
                if (bccomp($after, '999999999999999.999', 3) > 0) {
                    $this->fail('This entry exceeds the supported recorded-quantity limit for '.$line->item_name_snapshot.'.');
                }
                $balance->forceFill(['quantity' => $after])->save();
                (new StockMovement)->forceFill([
                    'stock_request_id' => $locked->id, 'stock_request_item_id' => $line->id, 'storage_area_id' => $locked->storage_area_id, 'item_id' => $line->item_id,
                    'movement_type' => $locked->operation_type->value, 'quantity_delta' => $delta, 'quantity_before' => $before, 'quantity_after' => $after,
                    'executed_by' => $user->id, 'executed_at' => $now, 'created_at' => $now,
                ])->save();
            }
            $locked->forceFill(['status' => StockRequestStatus::Completed, 'executed_by' => $user->id, 'executed_at' => $now])->save();
            $this->event($locked, $user, 'executed', StockRequestStatus::Approved);
        });
    }

    /**
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return T
     */
    private function transaction(\Closure $callback): mixed
    {
        try {
            return DB::transaction($callback, 3);
        } catch (QueryException|DeadlockException $exception) {
            if (in_array($exception->getCode(), ['40001', '40P01', '55P03', '23505'], true)) {
                $this->fail('A concurrent stock change prevented this action. The transaction was rolled back. Refresh the request and retry.');
            }
            throw $exception;
        }
    }

    private function actor(User $actor): User
    {
        $user = User::lockForUpdate()->findOrFail($actor->id);
        abort_unless($user->is_active, 403);
        // A request already in flight must not outlive a role/status revocation.
        abort_unless($user->auth_version === $actor->auth_version, 403);

        return $user;
    }

    /** @return array{StockRequest,User} */
    private function lock(StockRequest $request, User $actor): array
    {
        $locked = StockRequest::lockForUpdate()->findOrFail($request->id);
        $user = $this->actor($actor);
        // Serialize against responsibility and depot-status writers before authorizing.
        StorageArea::lockForUpdate()->findOrFail($locked->storage_area_id);
        Gate::forUser($user)->authorize('view', $locked);

        return [$locked, $user];
    }

    private function expect(StockRequest $request, StockRequestStatus $status): void
    {
        if ($request->status !== $status) {
            $this->fail('This action requires a '.$status->label().' request. Its current status is '.$request->status->label().'. Refresh the page; no stock was changed.');
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['operation' => $message]);
    }

    private function event(StockRequest $request, User $actor, string $type, ?StockRequestStatus $previous, ?string $comment = null): void
    {
        (new StockRequestEvent)->forceFill(['stock_request_id' => $request->id, 'actor_id' => $actor->id, 'event_type' => $type, 'previous_status' => $previous?->value,
            'new_status' => $request->status->value, 'comment' => $comment, 'created_at' => now()])->save();
    }

    /** @param array<int,array{item_id:int,quantity:string}> $lines */
    private function saveLines(StockRequest $request, array $lines): void
    {
        $ids = array_column($lines, 'item_id');
        if (count($ids) !== count(array_unique($ids))) {
            $this->fail('An item may appear only once per request.');
        }
        $items = Item::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $prepared = [];
        foreach ($lines as $line) {
            $item = $items->get($line['item_id']);
            if (! $item || ! $item->is_active || $item->tracking_mode === TrackingMode::Individual) {
                $this->fail('Select active quantity-based catalog items only.');
            }
            if (! StockQuantity::valid($line['quantity'])) {
                $this->fail('Every requested quantity must be a positive decimal with at most three decimal places.');
            }
            $prepared[] = ['stock_request_id' => $request->id, 'item_id' => $item->id, 'quantity' => bcadd($line['quantity'], '0', 3),
                'item_reference_snapshot' => $item->reference_code, 'item_name_snapshot' => $item->name, 'unit_snapshot' => $item->unit_of_measure];
        }
        $request->lines()->delete();
        foreach ($prepared as $attributes) {
            (new StockRequestItem)->forceFill($attributes)->save();
        }
    }

    /** @return Collection<int,StockRequestItem> */
    private function checkedLines(StockRequest $request, bool $submission): Collection
    {
        $lines = $request->lines()->get();
        if ($lines->isEmpty()) {
            $this->fail('Add at least one item before submitting or executing.');
        }
        $items = Item::whereIn('id', $lines->pluck('item_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        foreach ($lines as $line) {
            $item = $items->get($line->item_id);
            if (! $item || $item->tracking_mode === TrackingMode::Individual || ($submission && ! $item->is_active)) {
                $this->fail('One or more items are no longer eligible quantity-based items. Review the catalog before submission.');
            }
            if ($item->unit_of_measure !== $line->unit_snapshot) {
                $this->fail('An item unit changed. The request cannot execute with a different unit.');
            }
            if ($submission) {
                $line->forceFill(['item_reference_snapshot' => $item->reference_code, 'item_name_snapshot' => $item->name])->save();
            }
        }

        return $lines;
    }

    /** @param Collection<int,StockRequestItem> $lines
     * @return Collection<int,StorageAreaInventory>
     */
    private function availability(StockRequest $request, Collection $lines): Collection
    {
        $balances = StorageAreaInventory::where('storage_area_id', $request->storage_area_id)->whereIn('item_id', $lines->pluck('item_id'))
            ->orderBy('item_id')->lockForUpdate()->get()->keyBy('item_id');
        foreach ($lines as $line) {
            $balance = $balances->get($line->item_id);
            if (! $balance || ! $balance->initialized_at) {
                $this->fail($line->item_name_snapshot.': recorded stock is not initialized. A controlled opening-balance process is required before execution; exits also require initialized stock before submission.');
            }
            if ($request->operation_type === StockOperation::Exit && bccomp($balance->quantity, $line->quantity, 3) < 0) {
                $this->fail($line->item_name_snapshot.': insufficient recorded stock (available '.$balance->quantity.' '.$line->unit_snapshot.'). No quantities were changed; approved requests remain approved.');
            }
        }

        return $balances;
    }
}
