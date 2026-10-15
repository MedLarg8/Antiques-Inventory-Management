<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyStorageAreaController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('storage-areas/mine', [
            'assignment' => $request->user()->storageAreaAssignments()->whereNull('ended_at')->with('storageArea.city:id,name,is_active')->first(),
        ]);
    }
}
