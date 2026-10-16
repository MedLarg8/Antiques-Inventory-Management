<?php

namespace App\Http\Controllers;

use App\Actions\StockWorkflow;
use App\Enums\StockOperation;
use App\Enums\StockRequestStatus;
use App\Enums\TrackingMode;
use App\Http\Requests\SaveStockDraftRequest;
use App\Http\Requests\StockIndexRequest;
use App\Http\Requests\StockTransitionRequest;
use App\Models\City;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\StockRequest;
use App\Models\StorageArea;
use App\Models\StorageAreaInventory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class StockRequestController extends Controller
{
    public function index(StockIndexRequest $request): Response
    {
        $admin = $request->user()->can('access-administration');
        $approval = $request->routeIs('approval-requests');
        if ($approval) {
            Gate::authorize('access-administration');
        }
        $filters = $request->validated();
        if ($approval && ! $request->has('status')) {
            $filters['status'] = 'pending_approval';
        }
        $query = StockRequest::visibleTo($request->user())->with(['storageArea.city:id,name', 'requester:id,name'])->withCount('lines');
        if ($search = $filters['search'] ?? null) {
            $query->whereLike('request_number', "%{$search}%");
        }
        foreach (['status', 'operation_type', 'storage_area_id'] as $key) {
            if (! empty($filters[$key])) {
                $query->where($key, $filters[$key]);
            }
        }
        if (! empty($filters['city_id'])) {
            $query->whereHas('storageArea', fn ($q) => $q->where('city_id', $filters['city_id']));
        }
        if ($admin && ($employee = $filters['employee'] ?? null)) {
            $query->whereHas('requester', fn ($q) => $q->where(fn ($q) => $q->whereLike('name', "%{$employee}%")->orWhereLike('email', "%{$employee}%")));
        }
        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }
        if (($filters['scope'] ?? null) === 'mine') {
            $query->where('requested_by', $request->user()->id);
        }
        if (($filters['scope'] ?? null) === 'execution') {
            $query->where('status', StockRequestStatus::Approved)->whereHas('storageArea.currentAssignment', fn ($q) => $q->where('user_id', $request->user()->id));
        }

        return Inertia::render('stock-requests/index', [
            'requests' => $query->latest('id')->paginate(15)->withQueryString(), 'filters' => $filters, 'canReview' => $admin, 'canCreate' => $request->user()->can('create', StockRequest::class),
            'areas' => $admin ? StorageArea::orderBy('name')->get(['id', 'name']) : [],
            'cities' => $admin ? City::orderBy('name')->get(['id', 'name']) : [], 'approval' => $approval, 'statuses' => $this->statuses(),
        ]);
    }

    /** @return array<string,string> */
    private function statuses(): array
    {
        return collect(StockRequestStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all();
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', StockRequest::class);

        return Inertia::render('stock-requests/form', ['stockRequest' => null, 'area' => $request->user()->currentStorageArea()->with('city')->firstOrFail()]);
    }

    public function store(SaveStockDraftRequest $request, StockWorkflow $workflow): RedirectResponse
    {
        $created = $workflow->create($request->user(), $request->draftData());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Draft saved. Submit it for central approval when ready.']);

        return to_route('stock-requests.show', $created);
    }

    public function edit(StockRequest $stockRequest): Response
    {
        Gate::authorize('update', $stockRequest);

        return Inertia::render('stock-requests/form', ['stockRequest' => $stockRequest->load('lines'), 'area' => $stockRequest->storageArea()->with('city')->firstOrFail()]);
    }

    public function update(SaveStockDraftRequest $request, StockRequest $stockRequest, StockWorkflow $workflow): RedirectResponse
    {
        $workflow->update($request->user(), $stockRequest, $request->draftData());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Draft saved. No stock quantities changed.']);

        return to_route('stock-requests.show', $stockRequest);
    }

    public function show(Request $request, StockRequest $stockRequest): Response
    {
        Gate::authorize('view', $stockRequest);
        $stockRequest->load(['storageArea.city', 'requester:id,name', 'reviewer:id,name', 'executor:id,name', 'lines']);
        $canSeeArea = $request->user()->can('view', $stockRequest->storageArea);
        $balances = $canSeeArea ? StorageAreaInventory::where('storage_area_id', $stockRequest->storage_area_id)->whereIn('item_id', $stockRequest->lines->pluck('item_id'))->get()->mapWithKeys(fn ($b) => [$b->item_id => $b->toView()]) : null;
        $issues = [];
        if ($balances !== null) {
            foreach ($stockRequest->lines as $line) {
                $balance = $balances->get($line->item_id);
                if (! $balance || $balance['quantity'] === null) {
                    $issues[] = $line->item_name_snapshot.': recorded stock is not initialized. A controlled opening balance is required.';
                } elseif ($stockRequest->operation_type === StockOperation::Exit && bccomp($balance['quantity'], $line->quantity, 3) < 0) {
                    $issues[] = $line->item_name_snapshot.': insufficient recorded stock.';
                } elseif ($stockRequest->operation_type === StockOperation::Entry && bccomp(bcadd($balance['quantity'], $line->quantity, 3), '999999999999999.999', 3) > 0) {
                    $issues[] = $line->item_name_snapshot.': this entry exceeds the recorded-quantity limit.';
                }
            }
        }

        return Inertia::render('stock-requests/show', [
            'stockRequest' => $stockRequest, 'events' => $stockRequest->events()->with('actor:id,name')->paginate(20, ['*'], 'events_page')->withQueryString(),
            'movements' => $stockRequest->movements()->with('line', 'executor:id,name')->orderBy('id')->get(),
            'availability' => $balances, 'statuses' => $this->statuses(), 'executionIssues' => $issues,
            'actions' => ['create' => $request->user()->can('create', StockRequest::class), 'edit' => $request->user()->can('update', $stockRequest), 'cancel' => $request->user()->can('cancel', $stockRequest), 'review' => $request->user()->can('review', $stockRequest), 'execute' => $request->user()->can('execute', $stockRequest)],
        ]);
    }

    public function submit(StockTransitionRequest $request, StockRequest $stockRequest, StockWorkflow $workflow): RedirectResponse
    {
        $workflow->submit($request->user(), $stockRequest);

        return $this->done($stockRequest, 'Submitted for approval. Stock is unchanged and has not been reserved.');
    }

    public function approve(StockTransitionRequest $request, StockRequest $stockRequest, StockWorkflow $workflow): RedirectResponse
    {
        $workflow->approve($request->user(), $stockRequest);

        return $this->done($stockRequest, 'Approved. The responsible employee must confirm physical execution before stock changes.');
    }

    public function reject(StockTransitionRequest $request, StockRequest $stockRequest, StockWorkflow $workflow): RedirectResponse
    {
        $workflow->reject($request->user(), $stockRequest, $request->validated('rejection_reason'));

        return $this->done($stockRequest, 'Request rejected. The decision and reason have been recorded.');
    }

    public function cancel(StockTransitionRequest $request, StockRequest $stockRequest, StockWorkflow $workflow): RedirectResponse
    {
        $workflow->cancel($request->user(), $stockRequest);

        return $this->done($stockRequest, 'Draft cancelled. No stock was changed.');
    }

    public function execute(StockTransitionRequest $request, StockRequest $stockRequest, StockWorkflow $workflow): RedirectResponse
    {
        $workflow->execute($request->user(), $stockRequest);

        return $this->done($stockRequest, 'Execution recorded. All quantities and movement records were committed together.');
    }

    private function done(StockRequest $request, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('stock-requests.show', $request);
    }

    public function items(StockIndexRequest $request): JsonResponse
    {
        Gate::authorize('create', StockRequest::class);
        $area = $request->user()->currentStorageArea()->firstOrFail();
        $query = Item::where('is_active', true)->where('tracking_mode', '!=', TrackingMode::Individual);
        if ($search = $request->validated('search')) {
            $query->where(fn ($q) => $q->whereLike('name', "%{$search}%")->orWhereLike('reference_code', "%{$search}%"));
        }
        $items = $query->orderBy('name')->orderBy('id')->limit(20)->get(['id', 'name', 'reference_code', 'unit_of_measure']);
        $balances = StorageAreaInventory::where('storage_area_id', $area->id)->whereIn('item_id', $items->pluck('id'))->get()->keyBy('item_id');

        return response()->json(['items' => $items->map(fn ($item) => [...$item->toArray(), 'availability' => $balances->get($item->id)?->toView()])]);
    }

    public function movements(StockIndexRequest $request): Response
    {
        $filters = $request->validated();
        $admin = $request->user()->can('access-administration');
        $query = StockMovement::visibleTo($request->user())->with(['stockRequest:id,request_number,requested_by,executed_by,status,storage_area_id', 'line', 'storageArea.city:id,name', 'executor:id,name']);
        if (! empty($filters['operation_type'])) {
            $query->where('movement_type', $filters['operation_type']);
        }
        if (! empty($filters['storage_area_id'])) {
            $query->where('storage_area_id', $filters['storage_area_id']);
        }
        if (! empty($filters['city_id'])) {
            $query->whereHas('storageArea', fn ($q) => $q->where('city_id', $filters['city_id']));
        }
        if (! empty($filters['from'])) {
            $query->whereDate('executed_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('executed_at', '<=', $filters['to']);
        }

        $page = $query->latest('id')->paginate(20)->withQueryString();
        $visibleIds = StockRequest::visibleTo($request->user())->whereIn('id', $page->getCollection()->pluck('stock_request_id'))->pluck('id');

        return Inertia::render('stock-requests/movements', [
            'movements' => $page->through(function (StockMovement $movement) use ($visibleIds) {
                return [...$movement->toArray(), 'canViewRequest' => $visibleIds->contains($movement->stock_request_id)];
            }),
            'filters' => $filters, 'canReview' => $admin, 'areas' => $admin ? StorageArea::orderBy('name')->get(['id', 'name']) : [], 'cities' => $admin ? City::orderBy('name')->get(['id', 'name']) : [],
        ]);
    }
}
