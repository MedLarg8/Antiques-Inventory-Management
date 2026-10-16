<?php

namespace App\Http\Controllers;

use App\Enums\TrackingMode;
use App\Http\Requests\CatalogRequest;
use App\Models\Item;
use App\Models\ItemAsset;
use App\Models\ItemCategory;
use App\Models\ItemImage;
use App\Models\StorageArea;
use App\Models\StorageAreaInventory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['search' => 'nullable|string|max:100', 'category_id' => 'nullable|integer|exists:item_categories,id', 'tracking_mode' => 'nullable|in:quantity_consumable,quantity_reusable,individual_asset', 'status' => 'nullable|in:active,inactive']);
        $query = Item::visibleTo($request->user())->with(['category', 'primaryImage']);
        if ($search = $filters['search'] ?? null) {
            $query->where(fn ($q) => $q->whereLike('name', "%{$search}%")->orWhereLike('reference_code', "%{$search}%"));
        }
        foreach (['category_id', 'tracking_mode'] as $key) {
            if (! empty($filters[$key])) {
                $query->where($key, $filters[$key]);
            }
        }
        if ($request->user()->can('access-administration') && ! empty($filters['status'])) {
            $query->where('is_active', $filters['status'] === 'active');
        }

        return Inertia::render('inventory/index', [
            'items' => $query->orderBy('name')->orderBy('id')->paginate(15)->withQueryString()->through(function (Item $item) {
                return array_merge($item->toArray(), ['thumbnail' => $item->primaryImage?->toView()['thumbnail_url']]);
            }),
            'categories' => ItemCategory::orderBy('name')->get(['id', 'name', 'parent_id', 'is_active']), 'filters' => $filters, 'canManage' => $request->user()->can('access-administration'),
        ]);
    }

    public function categories(Request $request): Response
    {
        $filters = $request->validate(['search' => 'nullable|string|max:100']);
        $query = ItemCategory::with('parent:id,name');
        if ($search = $filters['search'] ?? null) {
            $query->where(fn ($q) => $q->whereLike('name', "%{$search}%")->orWhereLike('code', "%{$search}%"));
        }

        return Inertia::render('inventory/categories', ['categories' => $query->orderBy('name')->paginate(15)->withQueryString(), 'filters' => $filters, 'canManage' => $request->user()->can('access-administration')]);
    }

    public function form(Request $request, string $kind, ?int $record = null): Response
    {
        $model = match ($kind) {
            'categories' => new ItemCategory, 'assets' => new ItemAsset, default => new Item
        };
        if ($record) {
            $model = $model->findOrFail($record);
        }
        $parent = null;
        if ($kind === 'assets') {
            $parent = $model instanceof ItemAsset && $record ? $model->item : Item::findOrFail($request->integer('item'));
            abort_unless($parent->tracking_mode === TrackingMode::Individual, 422);
        }

        return Inertia::render('inventory/form', ['kind' => $kind, 'record' => $record ? $model : null, 'parent' => $parent, 'categories' => ItemCategory::orderBy('name')->get(['id', 'name', 'is_active', 'parent_id']), 'units' => config('inventory.units')]);
    }

    public function save(CatalogRequest $request, string $kind, ?int $record = null): RedirectResponse
    {
        $data = $request->validated();
        $model = DB::transaction(function () use ($kind, $record, $data, $request) {
            if ($kind === 'categories') {
                if (DB::getDriverName() === 'pgsql') {
                    DB::select('SELECT pg_advisory_xact_lock(31415927)');
                }
                $model = $record ? ItemCategory::lockForUpdate()->findOrFail($record) : new ItemCategory;
                $ancestor = $data['parent_id'] ?? null;
                $seen = [];
                while ($ancestor) {
                    if ((int) $ancestor === $model->id || isset($seen[$ancestor])) {
                        throw ValidationException::withMessages(['parent_id' => 'A category cannot be its own ancestor.']);
                    }
                    $seen[$ancestor] = true;
                    $ancestor = ItemCategory::whereKey($ancestor)->firstOrFail()->parent_id;
                }
            } elseif ($kind === 'assets') {
                $model = $record ? ItemAsset::findOrFail($record) : new ItemAsset;
                $item = Item::lockForUpdate()->findOrFail($record ? $model->item_id : $request->integer('item'));
                if ($item->tracking_mode !== TrackingMode::Individual) {
                    throw ValidationException::withMessages(['asset_reference' => 'Only individually tracked items can have asset records.']);
                }
                $model->item_id = $item->id;
            } else {
                $model = $record ? Item::lockForUpdate()->findOrFail($record) : new Item;
                if ($record && $model->tracking_mode->value !== $data['tracking_mode']) {
                    if (($data['tracking_mode'] === TrackingMode::Individual->value && $model->balances()->exists()) || ($data['tracking_mode'] !== TrackingMode::Individual->value && $model->assets()->exists())) {
                        throw ValidationException::withMessages(['tracking_mode' => 'Existing balance or asset records prevent this tracking-mode change.']);
                    }
                }
                if ($record && $model->unit_of_measure !== $data['unit_of_measure'] && $model->balances()->exists()) {
                    throw ValidationException::withMessages(['unit_of_measure' => 'Units cannot change once balance records exist.']);
                }
            }
            $model->fill($data)->save();

            return $model;
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Catalog record saved.']);

        return redirect($kind === 'categories' ? '/categories' : ($kind === 'assets' ? '/assets/'.$model->id : '/inventory/'.$model->id));
    }

    public function show(Request $request, Item $item): Response
    {
        Gate::authorize('view', $item);
        $admin = $request->user()->can('access-administration');

        return Inertia::render('inventory/show', [
            'item' => $item->load('category'), 'images' => $item->images->map(fn (ItemImage $image) => $image->toView()), 'canManage' => $admin,
            'balances' => $item->balances()->visibleTo($request->user())->with('storageArea.city')->orderBy('id')->paginate(15, ['*'], 'balances_page')->withQueryString()->through(fn (StorageAreaInventory $balance) => $balance->toView()),
            'assets' => $admin ? $item->assets()->orderBy('asset_reference')->paginate(15, ['*'], 'assets_page')->withQueryString() : null,
            'areas' => $admin ? StorageArea::with('city:id,name')->orderBy('name')->get() : [],
        ]);
    }

    public function asset(ItemAsset $asset): Response
    {
        Gate::authorize('view', $asset);

        return Inertia::render('inventory/asset', ['asset' => $asset->load('item'), 'images' => $asset->images->map(fn (ItemImage $image) => $image->toView())]);
    }

    public function threshold(Request $request, Item $item): RedirectResponse
    {
        $data = $request->validate(['storage_area_id' => 'required|integer|exists:storage_areas,id', 'minimum_quantity' => ['present', 'nullable', 'regex:/^\\d{1,15}(\\.\\d{1,3})?$/'], 'quantity' => 'prohibited', 'initialized_at' => 'prohibited']);
        DB::transaction(function () use ($item, $data) {
            $locked = Item::lockForUpdate()->findOrFail($item->id);
            if ($locked->tracking_mode === TrackingMode::Individual) {
                throw ValidationException::withMessages(['minimum_quantity' => 'Individual assets do not have quantity balances.']);
            }
            $balance = StorageAreaInventory::where('item_id', $item->id)->where('storage_area_id', $data['storage_area_id'])->first() ?? new StorageAreaInventory;
            $balance->forceFill(['item_id' => $item->id, 'storage_area_id' => $data['storage_area_id']]);
            $balance->minimum_quantity = $data['minimum_quantity'];
            $balance->save();
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Threshold configuration saved. Physical stock has not changed.']);

        return back();
    }

    public function area(Request $request, StorageArea $storageArea): Response
    {
        Gate::authorize('view', $storageArea);

        return Inertia::render('inventory/area', [
            'area' => $storageArea->load('city'),
            'balances' => StorageAreaInventory::where('storage_area_id', $storageArea->id)->with('item.category')->orderBy('id')->paginate(15)->through(fn (StorageAreaInventory $balance) => $balance->toView()),
        ]);
    }
}
