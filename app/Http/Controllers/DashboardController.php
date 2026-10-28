<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $internal = Gate::allows('dashboard.internal.view');
        abort_unless($internal || Gate::allows('dashboard.client.view'), 403);

        return view('dashboard', ['internal' => $internal]);
    }
}
