<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\OrganizationIndexRequest;
use App\Http\Requests\SaveStorageAreaRequest;
use App\Models\City;
use App\Models\StorageArea;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StorageAreaController extends Controller
{
    public function index(OrganizationIndexRequest $request): Response
    {
        $search = $request->string('search')->toString();

        return Inertia::render('storage-areas/index', [
            'areas' => StorageArea::query()->with(['city:id,name', 'currentAssignment.user:id,name'])
                ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->whereLike('name', '%'.$search.'%')->orWhereLike('code', '%'.$search.'%')->orWhereLike('location_details', '%'.$search.'%')))
                ->when($request->filled('city_id'), fn ($q) => $q->where('city_id', $request->integer('city_id')))
                ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->input('status') === 'active'))
                ->orderBy('name')->orderBy('id')->paginate(15)->withQueryString(),
            'cities' => City::orderBy('name')->get(['id', 'name', 'is_active']),
            'filters' => $request->only('search', 'city_id', 'status'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('storage-areas/form', ['area' => null, 'cities' => City::orderBy('name')->get(['id', 'name', 'is_active'])]);
    }

    public function store(SaveStorageAreaRequest $request): RedirectResponse
    {
        try {
            $area = StorageArea::create($request->validated());
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['code' => 'This storage-area code is already in use.']);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Storage area created.']);

        return to_route('storage-areas.show', $area);
    }

    public function show(Request $request, StorageArea $storage_area): Response
    {
        Gate::authorize('view', $storage_area);
        $admin = $request->user()->can('access-administration');

        return Inertia::render('storage-areas/show', [
            'area' => $storage_area->load(['city:id,name,is_active', 'currentAssignment.user:id,name']),
            'history' => $admin ? $storage_area->assignments()->whereNotNull('ended_at')->with('user:id,name')->orderByDesc('assigned_at')->orderByDesc('id')->paginate(10) : null,
            'employees' => $admin ? User::where('role', Role::StockEmployee)->whereDoesntHave('storageAreaAssignments', fn ($q) => $q->whereNull('ended_at'))->orderBy('name')->get(['id', 'name', 'email']) : [],
            'canManage' => $admin,
        ]);
    }

    public function edit(StorageArea $storage_area): Response
    {
        return Inertia::render('storage-areas/form', ['area' => $storage_area, 'cities' => City::orderBy('name')->get(['id', 'name', 'is_active'])]);
    }

    public function update(SaveStorageAreaRequest $request, StorageArea $storage_area): RedirectResponse
    {
        try {
            DB::transaction(function () use ($storage_area, $request): void {
                StorageArea::query()->lockForUpdate()->findOrFail($storage_area->id)->update($request->validated());
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['code' => 'This storage-area code is already in use.']);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Storage area updated. Existing assignments were preserved.']);

        return to_route('storage-areas.show', $storage_area);
    }
}
