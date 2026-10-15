<?php

namespace App\Http\Controllers;

use App\Actions\ChangeStorageAreaAssignment;
use App\Enums\Role;
use App\Http\Requests\ChangeAssignmentRequest;
use App\Http\Requests\OrganizationIndexRequest;
use App\Models\StorageArea;
use App\Models\StorageAreaAssignment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AssignmentController extends Controller
{
    public function index(OrganizationIndexRequest $request): Response
    {
        $search = $request->string('search')->toString();

        return Inertia::render('assignments/index', [
            'assignments' => StorageAreaAssignment::whereNull('ended_at')->with(['user:id,name,email', 'storageArea.city:id,name'])
                ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->whereHas('user', fn ($q) => $q->whereLike('name', '%'.$search.'%'))
                    ->orWhereHas('storageArea', fn ($q) => $q->whereLike('name', '%'.$search.'%')->orWhereLike('code', '%'.$search.'%'))))
                ->orderByDesc('assigned_at')->orderByDesc('id')->paginate(15)->withQueryString(),
            'unassigned' => User::where('role', Role::StockEmployee)->whereDoesntHave('storageAreaAssignments', fn ($q) => $q->whereNull('ended_at'))
                ->orderBy('name')->paginate(15, ['id', 'name', 'email'], 'employees_page')->withQueryString(),
            'filters' => ['search' => $search],
        ]);
    }

    public function update(ChangeAssignmentRequest $request, StorageArea $storage_area, ChangeStorageAreaAssignment $action): RedirectResponse
    {
        $action->handle($storage_area, $request->filled('user_id') ? $request->integer('user_id') : null,
            $request->filled('expected_assignment_id') ? $request->integer('expected_assignment_id') : null);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Responsibility updated. Assignment history has been retained.']);

        return to_route('storage-areas.show', $storage_area);
    }
}
