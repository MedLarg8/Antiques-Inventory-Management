<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ModuleController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $titles = [
            'inventory' => 'Inventory',
            'storage-areas' => 'Storage Areas',
            'stock-movements' => 'Stock Movements',
            'approval-requests' => 'Approval Requests',
            'documents' => 'Documents',
            'reports' => 'Reports',
            'administration' => 'Administration',
        ];

        $name = $request->route()->getName();
        abort_unless(isset($titles[$name]), 404);

        return Inertia::render('module-placeholder', [
            'title' => $titles[$name],
        ]);
    }
}
