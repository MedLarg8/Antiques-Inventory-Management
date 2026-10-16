<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\City;
use App\Models\Item;
use App\Models\ItemAsset;
use App\Models\ItemCategory;
use App\Models\StorageArea;
use App\Models\StorageAreaInventory;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $admin = $request->user()->can('access-administration');

        return Inertia::render('dashboard', [
            'inventory' => [
                'Visible item definitions' => Item::visibleTo($request->user())->count(),
                'Active item definitions' => Item::visibleTo($request->user())->where('is_active', true)->count(),
                'Categories' => $admin ? ItemCategory::count() : null,
                'Registered assets (location unconfirmed)' => $admin ? ItemAsset::count() : null,
                'Item types with initialized balances' => StorageAreaInventory::visibleTo($request->user())->whereNotNull('initialized_at')->distinct()->count('item_id'),
                'Low-stock balances' => StorageAreaInventory::visibleTo($request->user())->lowStock()->count(),
                'Uninitialized balance records' => StorageAreaInventory::visibleTo($request->user())->whereNull('initialized_at')->count(),
            ],
            'employees' => $admin ? [
                'Total accounts' => User::count(),
                'Active accounts' => User::where('is_active', true)->count(),
                'Inactive accounts' => User::where('is_active', false)->count(),
                'Active administrators' => User::where('is_active', true)->where('role', Role::Admin)->count(),
                'Depot employees' => User::where('role', Role::StockEmployee)->count(),
                'Active depot employees without assignments' => User::where('role', Role::StockEmployee)->where('is_active', true)->doesntHave('currentStorageArea')->count(),
            ] : null,
            'organization' => $admin ? [
                'cities' => City::count(),
                'storageAreas' => StorageArea::count(),
                'activeStorageAreas' => StorageArea::where('is_active', true)->count(),
                'assignedStorageAreas' => StorageArea::has('currentAssignment')->count(),
                'unassignedStorageAreas' => StorageArea::doesntHave('currentAssignment')->count(),
            ] : null,
            'assignment' => $admin ? null : $request->user()->storageAreaAssignments()->whereNull('ended_at')->with('storageArea.city:id,name,is_active')->first(),
        ]);
    }
}
