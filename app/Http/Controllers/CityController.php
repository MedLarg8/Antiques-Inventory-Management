<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrganizationIndexRequest;
use App\Http\Requests\SaveCityRequest;
use App\Models\City;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CityController extends Controller
{
    public function index(OrganizationIndexRequest $request): Response
    {
        $search = $request->string('search')->toString();

        return Inertia::render('cities/index', [
            'cities' => City::query()->when($search !== '', fn ($query) => $query->where(fn ($q) => $q->whereLike('name', '%'.$search.'%')->orWhereLike('code', '%'.$search.'%')))
                ->withCount('storageAreas')->orderBy('name')->orderBy('id')->paginate(15)->withQueryString(),
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('cities/form', ['city' => null]);
    }

    public function store(SaveCityRequest $request): RedirectResponse
    {
        try {
            City::create($request->validated());
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['code' => 'This city code is already in use.']);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'City created.']);

        return to_route('cities.index');
    }

    public function edit(City $city): Response
    {
        return Inertia::render('cities/form', ['city' => $city]);
    }

    public function update(SaveCityRequest $request, City $city): RedirectResponse
    {
        try {
            $city->update($request->validated());
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['code' => 'This city code is already in use.']);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'City updated.']);

        return to_route('cities.index');
    }
}
