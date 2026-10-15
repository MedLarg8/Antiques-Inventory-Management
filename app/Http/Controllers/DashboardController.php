<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\StorageArea;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $admin = $request->user()->can('access-administration');

        return Inertia::render('dashboard', [
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
