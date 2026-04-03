<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController
{
    public function __invoke(Request $request): View
    {
        return view('admin.dashboard');
    }
}
